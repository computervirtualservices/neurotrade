<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetPair;
use App\Models\TradeSignal;
use App\Services\CryptoML\Contracts\MetricsCalculatorInterface;
use App\Services\CryptoML\Contracts\MomentumDetectorInterface;
use App\Services\CryptoML\Contracts\RecommendationBuilderInterface;
use App\Services\CryptoML\Contracts\SupportResistanceFinderInterface;
use App\Services\CryptoML\SignalLabeler;
use Illuminate\Support\Facades\Log;
use Exception;

final class SignalLogger
{
    public function __construct(
        private readonly KrakenTicker                     $krakenTicker,
        private readonly KrakenBalance                   $krakenBalance,
        private readonly KrakenOrderService              $krakenOrderService,
        private readonly MetricsCalculatorInterface       $metrics,
        private readonly MomentumDetectorInterface        $momentum,
        private readonly SupportResistanceFinderInterface $srf,
        private readonly RecommendationBuilderInterface  $recBuilder,
    ) {}

    /**
     * @param  string $pairName
     * @param  array{
     *            signal: string,
     *            confidence: float,
     *            recommendation: array,
     *            interval: int,
     *            indicators?: array<int,array<string,mixed>>
     *         } $payload
     */
    public function logPercentage(string $pairName, array $payload): ?TradeSignal
    {
        try {
            $lastAction = TradeSignal::where('pair_name', $pairName)
                ->latest('id')
                ->value('action');

            // 0) Ensure we have enough indicator history
            $indicators = $payload['indicators'] ?? [];
            if (!is_array($indicators) || count($indicators) < 2) {
                Log::warning("Skipping log: no indicators for {$pairName}");
                return null;
            }

            // 1) Derive action from signal label
            if (isset($payload['signal'])) {
                $signal = $payload['signal'] ?? '';
                $nextSignal = $payload['next_result']['signal'] ?? '';
            } else {
                $signal = $payload['prediction'] ?? '';
                $nextSignal = $payload['next_result']['prediction'] ?? '';
            }

            // 2) Check if the signal is confident enough to be considered a trade signal
            $confidence = $payload['confidence'] ?? 0.0;

            // assume $signal (string), $confidence (float 0–1), and $indicators (most recent bar) are available
            $shortMA = end($indicators)['ema9'];   // or whichever you consider “trend”
            $downLongMA  = end($indicators)['smma9'];  // long‐term trend
            $upLongMA = end($indicators)['smma21'];  // long‐term trend

            // Heikin-Ashi confirmation
            // $haSeries = $this->makeHeikinAshi($indicators);
            // $lastHA   = end($haSeries);
            // $isHAUp   = $lastHA['close'] > $lastHA['open'];
            // $isHADown = $lastHA['close'] < $lastHA['open'];

            // 1) filter out low‐confidence predictions
            // if ($confidence < 0.6) {
            //     Log::info("Skipping {$pairName}: low confidence ({$confidence}) for {$signal}");
            //     return null;
            // }

            // 2) classify into BUY vs SELL with extra confirmation
            // Grab all the keys in ACTION_MAP whose first element is 'BUY'
            $buySignals  = $this->recBuilder->signalsForAction('BUY');
            $sellSignals = $this->recBuilder->signalsForAction('SELL');

            // 3) optionally “second‐layer” checks for milder signals
            if (in_array($signal, [SignalLabeler::PROFIT_UP_1, SignalLabeler::MOMENTUM_UP, SignalLabeler::UPTREND_START])) {
                // require short‐term trend bullish
                if (! ($shortMA > $downLongMA)) {
                    Log::info("Rejecting {$signal} for {$pairName}: trend not bullish (EMA9={$shortMA}, SMMA9={$downLongMA})");
                    return null;
                }
                $buySignals[] = $signal;
            }

            if (in_array($signal, [SignalLabeler::PROFIT_DOWN_1, SignalLabeler::MOMENTUM_DOWN, SignalLabeler::UPTREND_END])) {
                if (! ($shortMA < $upLongMA)) {
                    Log::info("Rejecting {$signal} for {$pairName}: trend not bearish (EMA9={$shortMA}, SMMA21={$upLongMA})");
                    return null;
                }
                $sellSignals[] = $signal;
            }

            // 4) final decision
            if (in_array($signal, $buySignals, true)) {
                Log::info("Buying {$pairName}: buy trade for signal {$signal}");
                $action = 'BUY';
            } elseif (in_array($signal, $sellSignals, true)) {
                Log::info("Selling {$pairName}: sell trade for signal {$signal}");
                $action = 'SELL';
            } else {
                Log::info("Skipping {$pairName}: no trade for signal {$signal}");
                $action = 'HOLD';
                return null;
            }

            // 4) next final decision
            if (in_array($nextSignal, $buySignals, true)) {
                Log::info("Buying {$pairName}: buy trade for next signal {$nextSignal}");
                $nextAction = 'BUY';
            } elseif (in_array($nextSignal, $sellSignals, true)) {
                Log::info("Selling {$pairName}: sell trade for next signal {$nextSignal}");
                $nextAction = 'SELL';
            } else {
                Log::info("Skipping {$pairName}: no trade for next signal {$nextSignal}");
                $nextAction = 'HOLD';
            }

            // Only allow BUY→BUY, SELL→SELL, or SELL→HOLD
            if (
                ! (
                    ($action === 'BUY'  && $nextAction === 'BUY')
                    || ($action === 'SELL' && in_array($nextAction, ['SELL', 'HOLD'], true))
                )
            ) {
                Log::info("Skipping {$pairName}: action {$action} → next action {$nextAction} not allowed");
                return null;
            }

            // 2) Confirmation filters: volume & momentum
            $latest = end($indicators);
            $currVol = $latest['volume'] ?? 0;
            $avgVol = $this->metrics->averageVolume($indicators, 30);

            // if ($currVol < 1.1 * $avgVol) {
            //     Log::debug("Skipping {$action}: volume {$currVol} < 1.1× avg {$avgVol}");
            //     return null;
            // }

            if ($lastAction !== 'BUY' && $action === 'BUY' && $nextAction === 'BUY' && !$this->momentum->up($indicators)) {
                Log::debug("Skipping BUY: no bullish momentum for {$pairName}");
                $action = 'HOLD';
                return null;
            }

            if ($action === 'SELL' && !$this->momentum->down($indicators)) {
                Log::debug("Skipping SELL: no bearish momentum for {$pairName}");
                $action = 'HOLD';
                return null;
            }

            // 3) Fetch live bid/ask from Kraken
            $ticker = $this->krakenTicker->fetchTickerData($pairName)['result'][$pairName];
            $askPrice = (float) str_replace(',', '', $ticker['a'][0]);
            $bidPrice = (float) str_replace(',', '', $ticker['b'][0]);

            // NEW: Skip if the bid/ask spread is > 2%
            $spread = ($askPrice - $bidPrice) / $askPrice;
            if ($spread > 0.02) {
                Log::warning("Skipping {$pairName}: spread too wide ({[round($spread*100, 2)]}% > 2%)");
                return null;
            }

            // 4) Compute support/resistance levels
            $priceForLevels = $action === 'BUY' ? $askPrice : $bidPrice;
            $levels = $this->srf->levels($indicators, $priceForLevels);

            // 5) Determine entry & order type
            if ($action === 'BUY') {
                $entry = ($levels['resistance'][0] ?? $askPrice) + 0.001;
                $orderType = 'stop-limit';
            } else {
                $entry = $levels['support'][0] ?? $bidPrice;
                $orderType = 'limit';
            }

            // 6) Calculate stop-loss: swing-low vs ATR, capped at 2% risk
            $swingStop = $levels['support'][0] ?? ($entry * 0.98);
            $atr = $latest['atr'] ?? 0.0;
            $atrStop = $entry - 1.5 * $atr;
            $stopLoss = max($swingStop, $atrStop);

            $riskPct = ($entry - $stopLoss) / $entry * 100;
            if ($riskPct > 2.0) {
                $stopLoss = $entry * 0.98;
            }

            // 7) Calculate take-profit: swing-high vs 2:1 RR
            $swingTarget = $levels['resistance'][0] ?? ($entry * 1.04);
            $rrTarget = $entry + 2 * ($entry - $stopLoss);
            $takeProfit = max($swingTarget, $rrTarget);

            // 8) Enforce BUY/SELL alternation (first must be BUY)
            if ((is_null($lastAction) && $action !== 'BUY') || ($lastAction === $action)) {
                Log::warning("Invalid signal for pair {$pairName} - last action was {$lastAction}");
                return null;
            }

            $assetPair = AssetPair::where('pair_name', $pairName)->first();

            // If you BUY, check Kraken if you have the funds then BUY the assets
            if ($action === 'BUY') {
                // $balance = $this->krakenBalance->getBalances();
                // sleep(1); // Kraken API rate limits to 1 request per second

                // $availableFunds = $balance['ZUSD'] ?? 0;
                // $total = 10;
                // $cost = $askPrice;
                // $lotDecimal = $assetPair->lot_decimals;
                // $volume = $this->truncateDecimal($total / $cost, $lotDecimal); //lot_decimal

                // if ($availableFunds < $cost) {
                //     Log::warning("Insufficient funds for {$pairName}: available {$availableFunds}, required {$cost}");
                //     return null;
                // }

                // Execute the buy order here
                // $orderResponse = $this->krakenOrderService->addPostOnlyLimitOrder(
                //     $assetPair->ws_name,
                //     strtolower($action),
                //     $volume,
                //     $cost
                // );
            } else if ($action === 'SELL') {
                // Execute the sell order here
                // $balance = $this->krakenBalance->getBalances();
                // sleep(1); // Kraken API rate limits to 1 request per second
                // $baseCurrency = $assetPair->base_currency;

                // // if $basecurrency is in the balance array then we need to sell from it


                // $availableFunds = $balance[$baseCurrency] ?? 0;
                // $cost = $bidPrice;
                // $lotDecimal = $assetPair->lot_decimals;
                // $volume = $this->truncateDecimal((float)$availableFunds, (int)$lotDecimal); //lot_decimal               

                // Execute the sell order here
                // $orderResponse = $this->krakenOrderService->addPostOnlyLimitOrder(
                //     $assetPair->ws_name,
                //     strtolower($action),
                //     $volume,
                //     $cost
                // );
            }


            // 9) Persist the signal
            $signalModel = TradeSignal::create([
                'pair_name'             => $pairName,
                'interval'              => $payload['interval'],
                'signal'                => $signal,
                'confidence'            => $confidence,
                'action'                => $action,
                'strength'              => $payload['recommendation']['strength'] ?? null,
                'confidence_percent'    => $payload['recommendation']['confidence'] ?? null,
                'confidence_level'      => $payload['recommendation']['confidence_level'] ?? null,
                'explanation'           => $payload['recommendation']['explanation'] ?? null,
                'suggested_entry'       => round($entry, 6),
                'suggested_stop_loss'   => round($stopLoss, 6),
                'suggested_take_profit' => round($takeProfit, 6),
                'order_type'            => $orderType,
                'buy_price'             => $askPrice,
                'sell_price'            => $bidPrice,
                'support_levels'        => $levels['support'],
                'resistance_levels'     => $levels['resistance'],
                'key_indicators'        => $payload['recommendation']['key_indicators'] ?? [],
            ]);

            Log::info("[SignalLogger] {$action} logged for {$pairName}", [
                'entry'  => $entry,
                'stop'   => $stopLoss,
                'target' => $takeProfit,
                'order'  => $orderType,
            ]);

            return $signalModel;
        } catch (Exception $e) {
            Log::error("[SignalLogger] Error logging signal: {$e->getMessage()}", [
                'pair_name' => $pairName,
                'payload'   => $payload,
            ]);
            return null;
        }
    }

    public function heikinAshiPercentage(string $pairName, array $payload): ?TradeSignal
    {
        try {
            $lastAction = TradeSignal::where('pair_name', $pairName)
                ->latest('id')
                ->value('action');

            // 0) Ensure we have enough indicator history
            $indicators = $payload['indicators'] ?? [];
            if (!is_array($indicators) || count($indicators) < 2) {
                Log::warning("Skipping log: no indicators for {$pairName}");
                return null;
            }

            $nextIndicators = $payload['next_indicators'] ?? [];
            if (!is_array($nextIndicators) || count($nextIndicators) < 2) {
                Log::warning("Skipping log: no next indicators for {$pairName}");
                return null;
            }

            // 1) Derive action from signal label
            if (isset($payload['signal'])) {
                $signal = $payload['signal'] ?? '';
                $nextSignal = $payload['next_result']['signal'] ?? '';
            } else {
                $signal = $payload['prediction'] ?? '';
                $nextSignal = $payload['next_result']['prediction'] ?? '';
            }

            // 2) Check if the signal is confident enough to be considered a trade signal
            $confidence = $payload['confidence'] ?? 0.0;

            // assume $signal (string), $confidence (float 0–1), and $indicators (most recent bar) are available
            $shortMA = end($indicators)['ema9'];   // or whichever you consider “trend”
            $downLongMA  = end($indicators)['smma9'];  // long‐term trend
            $upLongMA = end($indicators)['smma21'];  // long‐term trend

            $nextShortMA = end($nextIndicators)['ema9'];   // or whichever you consider “trend”
            $nextDownLongMA  = end($nextIndicators)['smma9'];  // long‐term trend
            $nextUpLongMA = end($nextIndicators)['smma21'];  // long‐term trend


            // Heikin-Ashi confirmation
            $lastHA   = end($payload['heikin_ashi']) ?? [];
            $isHAUp   = $lastHA['close'] > $lastHA['open'];
            $isHADown = $lastHA['close'] < $lastHA['open'];

            $nextLastHA   = end($payload['next_heikin_ashi']) ?? [];
            $isNextHAUp   = $nextLastHA['close'] > $nextLastHA['open'];
            $isNextHADown = $nextLastHA['close'] < $nextLastHA['open'];

            // 1) filter out low‐confidence predictions
            // if ($confidence < 0.6) {
            //     Log::info("Skipping {$pairName}: low confidence ({$confidence}) for {$signal}");
            //     return null;
            // }

            // 2) classify into BUY vs SELL with extra confirmation
            // Grab all the keys in ACTION_MAP whose first element is 'BUY'
            $buySignals  = $this->recBuilder->signalsForAction('BUY');
            $sellSignals = $this->recBuilder->signalsForAction('SELL');


            // 4) final decision
            if (in_array($signal, $buySignals, true)) {
                Log::info("Buying {$pairName}: buy trade for signal {$signal}");
                $action = 'BUY';
            } elseif (in_array($signal, $sellSignals, true)) {
                Log::info("Selling {$pairName}: sell trade for signal {$signal}");
                $action = 'SELL';
            } else {
                Log::info("Skipping {$pairName}: no trade for signal {$signal}");
                $action = 'HOLD';
            }

            // 4) next final decision
            if (in_array($nextSignal, $buySignals, true)) {
                Log::info("Buying {$pairName}: buy trade for next signal {$nextSignal}");
                $nextAction = 'BUY';
            } elseif (in_array($nextSignal, $sellSignals, true)) {
                Log::info("Selling {$pairName}: sell trade for next signal {$nextSignal}");
                $nextAction = 'SELL';
            } else {
                Log::info("Skipping {$pairName}: no trade for next signal {$nextSignal}");
                $nextAction = 'HOLD';
            }

            // 3) Fetch live bid/ask from Kraken
            $ticker = $this->krakenTicker->fetchTickerData($pairName)['result'][$pairName];
            $askPrice = (float) str_replace(',', '', $ticker['a'][0]);
            $bidPrice = (float) str_replace(',', '', $ticker['b'][0]);

            // NEW: Skip if the bid/ask spread is > 2%
            $spread = ($askPrice - $bidPrice) / $askPrice;
            if ($spread > 0.02) {
                Log::warning("Skipping {$pairName}: spread too wide ({[round($spread*100, 2)]}% > 2%)");
                // return null;
            }

            // 4) Compute support/resistance levels
            $priceForLevels = $action === 'BUY' ? $askPrice : $bidPrice;
            $levels = $this->srf->levels($indicators, $priceForLevels);

            // 5) Determine entry & order type
            if ($action === 'BUY') {
                $entry = ($levels['resistance'][0] ?? $askPrice) + 0.001;
                $orderType = 'stop-limit';
            } else {
                $entry = $levels['support'][0] ?? $bidPrice;
                $orderType = 'limit';
            }

            // 6) Calculate stop-loss: swing-low vs ATR, capped at 2% risk
            $swingStop = $levels['support'][0] ?? ($entry * 0.98);
            $atr = $latest['atr'] ?? 0.0;
            $atrStop = $entry - 1.5 * $atr;
            $stopLoss = max($swingStop, $atrStop);

            $riskPct = ($entry - $stopLoss) / $entry * 100;
            if ($riskPct > 2.0) {
                $stopLoss = $entry * 0.98;
            }

            // 7) Calculate take-profit: swing-high vs 2:1 RR
            $swingTarget = $levels['resistance'][0] ?? ($entry * 1.04);
            $rrTarget = $entry + 2 * ($entry - $stopLoss);
            $takeProfit = max($swingTarget, $rrTarget);

            // 5) next final decision
            if ($isHAUp && ($shortMA > $upLongMA)) {
                if ($isNextHAUp && $nextShortMA > $nextUpLongMA) {
                    //dd("Heikin-Ashi Uptrend confirmed for {$pairName} and action is {$action}");
                    $action = 'BUY';
                } else {
                    Log::info("Skipping {$pairName}: next Heikin-Ashi not confirming uptrend");
                    return null;
                }
            } elseif ($isHADown && ($shortMA < $downLongMA)) {
                //dd("Heikin-Ashi Downtrend confirmed for {$pairName} and action is {$action}");
                $action = 'SELL';
            } else {
                Log::info("Skipping {$pairName}: no Heikin-Ashi confirmation");
                return null;
            }

            // 8) Enforce BUY/SELL alternation (first must be BUY)
            if ((is_null($lastAction) && $action !== 'BUY') || ($lastAction === $action)) {
                Log::warning("Invalid signal for pair {$pairName} - last action was {$lastAction}");
                return null;
            }

            //   dd($action,$isHAUp, $isHADown, $shortMA, $upLongMA, $nextShortMA, $nextUpLongMA);
            $assetPair = AssetPair::where('pair_name', $pairName)->first();

            // If you BUY, check Kraken if you have the funds then BUY the assets
            if ($action === 'BUY') {
                $balance = $this->krakenBalance->getBalances();
                sleep(1); // Kraken API rate limits to 1 request per second

                $availableFunds = $balance['ZUSD'] ?? 0;
                $total = 10;
                $cost = $askPrice;
                $lotDecimal = $assetPair->lot_decimals;
                $volume = $this->truncateDecimal($total / $cost, $lotDecimal); //lot_decimal

                if ($availableFunds < $cost) {
                    Log::warning("Insufficient funds for {$pairName}: available {$availableFunds}, required {$cost}");
                    return null;
                }

                // Execute the buy order here
                $orderResponse = $this->krakenOrderService->addPostOnlyLimitOrder(
                    $assetPair->ws_name,
                    strtolower($action),
                    $volume,
                    $cost
                );
            } else if ($action === 'SELL') {
                // Execute the sell order here
                $balance = $this->krakenBalance->getBalances();
                sleep(1); // Kraken API rate limits to 1 request per second
                $baseCurrency = $assetPair->base_currency;

                // if $basecurrency is in the balance array then we need to sell from it


                $availableFunds = $balance[$baseCurrency] ?? 0;
                $cost = $bidPrice;
                $lotDecimal = $assetPair->lot_decimals;
                $volume = $this->truncateDecimal((float)$availableFunds, (int)$lotDecimal); //lot_decimal               

                // Execute the sell order here
                $orderResponse = $this->krakenOrderService->addPostOnlyLimitOrder(
                    $assetPair->ws_name,
                    strtolower($action),
                    $volume,
                    $cost
                );
            }


            // 9) Persist the signal
            $signalModel = TradeSignal::create([
                'pair_name'             => $pairName,
                'interval'              => $payload['interval'],
                'signal'                => $signal,
                'confidence'            => $confidence,
                'action'                => $action,
                'strength'              => $payload['recommendation']['strength'] ?? null,
                'confidence_percent'    => $payload['recommendation']['confidence'] ?? null,
                'confidence_level'      => $payload['recommendation']['confidence_level'] ?? null,
                'explanation'           => $payload['recommendation']['explanation'] ?? null,
                'suggested_entry'       => round($entry, 6),
                'suggested_stop_loss'   => round($stopLoss, 6),
                'suggested_take_profit' => round($takeProfit, 6),
                'order_type'            => $orderType,
                'buy_price'             => $askPrice,
                'sell_price'            => $bidPrice,
                'support_levels'        => $levels['support'],
                'resistance_levels'     => $levels['resistance'],
                'key_indicators'        => $payload['recommendation']['key_indicators'] ?? [],
            ]);

            Log::info("[SignalLogger] {$action} logged for {$pairName}", [
                'entry'  => $entry,
                'stop'   => $stopLoss,
                'target' => $takeProfit,
                'order'  => $orderType,
            ]);

            unset(
                $ha,
                $nextHa,
                $last,
                $nextLast,
                $isHAUp,
                $isNextUp,
                $action,
                $lastAction,
                $ticker,
                $askPrice,
                $bidPrice,
                $assetPair,
                $volume,
                $base,
                $balance
            );

            return $signalModel;
        } catch (Exception $e) {
            Log::error("[SignalLogger] Error logging signal: {$e->getMessage()}", [
                'pair_name' => $pairName,
                'payload'   => $payload,
            ]);
            return null;
        }
    }

    /**
     * Truncate a float to a given number of decimal places, no rounding.
     */
    private function truncateDecimal(float $value, int $decimals): float
    {
        $factor = (int) pow(10, $decimals);
        return floor($value * $factor) / $factor;
    }
}
