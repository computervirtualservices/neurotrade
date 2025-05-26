<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Dispatcher, Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\AssetPair;
use App\Helpers\OHLCIndicators;
use App\Services\KrakenMarketData;
use App\Services\CryptoML\CryptoPredictor;
use App\Services\SignalLogger;
use App\Models\OhlcvData;
use App\Helpers\KrakenStructure;
use BadFunctionCallException;
use Laratrade\Trader\Contracts\Trader as TraderContract;
use Exception;
use Rubix\ML\Datasets\Labeled;
use InvalidArgumentException;
use Throwable;

final class ProcessCryptoPair implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private  TraderContract  $trader;
    private  KrakenMarketData $marketData;
    private  CryptoPredictor   $predictor;
    private  SignalLogger      $logger;
    private  string            $pairName;
    private  int               $interval;
    private  bool              $autoTrade;
    private  bool              $dryRun;

    public function __construct(
        string $pairName,
        int $interval,
        bool $autoTrade = false,
        bool $dryRun = false
    ) {
        $this->pairName = $pairName;
        $this->interval = $interval;
        $this->autoTrade = $autoTrade;
        $this->dryRun = $dryRun;
    }

    public function handle(
        TraderContract $trader,
        KrakenMarketData $marketData,
        CryptoPredictor $predictor,
        SignalLogger $logger
    ): void {
        Log::info("[ProcessCryptoPair] Starting {$this->pairName}@{$this->interval}m");

        $this->trader = $trader;
        $this->marketData = $marketData;
        $this->predictor = $predictor;
        $this->logger = $logger;
        $pair = AssetPair::where('ws_name', $this->pairName)->first();
        $rawInterval = $this->interval;

        $predictor->createModel($this->pairName, $this->interval);
        try {
            $predictor->resetState(); // Less intensive than full reinitialization

            $predictor->clearSessionData(); // Clean up without destroying the whole model

            $name = $pair->pair_name;
            $interval = (int)$rawInterval ?: $pair->interval;

            // ——— Normalize the interval ———
            if ($rawInterval !== null) {
                // numeric value like "15"?
                if (is_numeric($rawInterval)) {
                    $interval = (int) $rawInterval;
                } else {
                    // label like "15M", "1H", etc.
                    $interval = OHLCIndicators::minutes(strtoupper($rawInterval));
                }

                if ($interval <= 0) {
                    Log::error("Invalid interval ‘{$rawInterval}’. Skipping {$name}.");
                    return;
                }
            } else {
                // no CLI override → use pair’s configured interval
                $interval = (int)$pair->interval;
            }
            // Normalize and validate interval
            Log::info("Normalizing interval {$rawInterval} to {$interval}m");
            $interval = $this->normalizeInterval($rawInterval, $interval);
            if ($interval <= 0) {
                Log::error("Invalid interval “{$rawInterval}” for {$name}, skipping.");
                return;
            }
            Log::info("Processing {$name} at {$interval}m");

            try {
                // Fetch raw OHLC data
                $crossValidate = true;

                // Fetch primary TF
                $ohlc1       = $this->fetchOhlc($name, $interval);
                $heikinAshi1 = $this->buildHeikinAshi($ohlc1);
                $indicators1 = $this->buildTimeFrameIndicators($ohlc1, $interval);
                $interval = (int)$interval;
                // Determine and fetch next-higher supported TF
                $nextTf = OHLCIndicators::nextInterval($interval);

                $indicators2 = [];
                if ($nextTf !== null) {
                    Log::info("  Also fetching {$nextTf}m data");
                    $ohlc2        = $this->fetchOhlc($name, $nextTf);
                    $heikinAshi2 = $this->buildHeikinAshi($ohlc2);
                    $indicators2  = $this->buildTimeFrameIndicators($ohlc2, $nextTf);
                }

                // Train & predict multi-timeframe
                Log::info("Training model for {$name}@{$interval}m");

                //$predictor->trainMultiTF($ohlc1, $indicators1, $indicators2, $crossValidate, $interval, config('cryptoml.is_regressor'));
                $predictor->train($ohlc1, $indicators1, $crossValidate, $interval, config('cryptoml.is_regressor'));

                $currentPrice = end($ohlc1)['y'][3];
                Log::info('Predicting for ' . $name . ' at price: ' . $currentPrice);
                // $result = $predictor->predictMultiTF(
                //     $ohlc1,
                //     $indicators1,
                //     $indicators2,
                //     $currentPrice,
                //     $interval
                // );
                $result = $predictor->predict($ohlc1, $indicators1, $currentPrice, $interval);

                // Train & predict multi-timeframe
                Log::info("Training model for {$name}@{$nextTf}m");
                $predictor->train($ohlc2, $indicators2, $crossValidate, $nextTf, config('cryptoml.is_regressor'));
                $result2 = $predictor->predict($ohlc2, $indicators2, $currentPrice, $nextTf);

                // Log the signal
                $result['next_result'] = $result2;
                $result['heikin_ashi']   = $heikinAshi1;
                $result['next_heikin_ashi'] = $heikinAshi2;
                $result['indicators']      = $indicators1;
                $result['next_indicators'] = $indicators2;
                $result['interval']        = $interval;

                // $logger->logPercentage($name, $result);
                $logger->heikinAshiPercentage($name, $result);
            } catch (BadFunctionCallException $e) {
                Log::error("Trader function call failed for {$name}.");
            } catch (InvalidArgumentException $e) {
                Log::error("{$e->getMessage()} — skipping {$name}.");
            } catch (\Throwable $e) {
                Log::error("Error processing {$name}: {$e->getMessage()}", ['exception' => $e]);
            }
        } catch (InvalidArgumentException $e) {
            Log::error("Invalid argument for {$this->pairName}@{$this->interval}: {$e->getMessage()}");
        } catch (\Throwable $e) {
            Log::error("Error in ProcessCryptoPair for {$this->pairName}@{$this->interval}: {$e->getMessage()}");
        } finally {
            // Clean up
            unset($raw);
            unset($ohlc1, $indicators1);
            unset($ohlc2, $indicators2, $newRows);
            gc_collect_cycles();
        }
    }

    private function upsertNewTicks(string $pair, int $interval, array $raw): void
    {
        Log::info("[ProcessCryptoPair] Upserting new ticks for {$pair}@{$interval}");

        try {
            $tableName = (string) Str::of($pair)
                ->lower()
                ->replace('/', '_')
                ->append('_ohlcv');

            $existing = DB::table($tableName)
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->pluck('timestamp')
                ->map(fn($ts) => (int) $ts)
                ->toArray();

            $newRows = [];
            foreach ($raw as $row) {
                $ts = $row[0] ?? null;
                if ($ts !== null && !in_array($ts, $existing, true)) {
                    $newRows[] = [
                        'pair'        => $pair,
                        'interval'    => $interval,
                        'timestamp'   => $ts,
                        'open_price'  => $row[1],
                        'high_price'  => $row[2],
                        'low_price'   => $row[3],
                        'close_price' => $row[4],
                        'vwap'        => $row[5] ?? 0,
                        'volume'      => $row[6] ?? 0,
                        'trade_count' => $row[7] ?? null,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }
            }

            if (!empty($newRows)) {
                foreach (array_chunk($newRows, 1000) as $chunk) {
                    DB::table($tableName)->upsert(
                        $chunk,
                        ['pair', 'interval', 'timestamp'],
                        ['open_price', 'high_price', 'low_price', 'close_price', 'vwap', 'volume', 'trade_count', 'updated_at']
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::error("upsertNewTicks failed for {$pair}@{$interval}: {$e->getMessage()}");
        }
    }

    private function buildTimeFrameIndicators(array $ohlc, $interval): array
    {
        $length = count($ohlc);

        // 1) Preallocate and extract OHLCV arrays
        $opens = $highs = $lows = $closes = $vols = array_fill(0, $length, 0.0);
        for ($i = 0; $i < $length; ++$i) {
            [$o, $h, $l, $c, $v] = $ohlc[$i]['y'];
            $opens[$i]  = (float) $o;
            $highs[$i]  = (float) $h;
            $lows[$i]   = (float) $l;
            $closes[$i] = (float) $c;
            $vols[$i]   = (float) $v;
        }

        if (end($closes) > 0.001) {
            $scale = fn($v) => $v * 1000;

            $opensMod  = array_map($scale, $opens);
            $highsMod  = array_map($scale, $highs);
            $lowsMod   = array_map($scale, $lows);
            $closesMod = array_map($scale, $closes);

            // reset the internal pointer if you used end()
            reset($closes);
        } else {
            $opensMod  = $opens;
            $highsMod  = $highs;
            $lowsMod   = $lows;
            $closesMod = $closes;
        }

        // 2) Compute new indicators on full series
        $retPeriod   = config("trader{$interval}m.ret_period");
        $logReturns  = $this->seriesLogReturns($closesMod, $retPeriod);

        $priceRelHigh = $this->trader->div(
            $closesMod,
            $this->trader->max($highsMod, config("trader{$interval}m.price_rel_period"))
        );

        $priceRelLow  = $this->trader->div(
            $closesMod,
            $this->trader->min($lowsMod, config("trader{$interval}m.price_rel_period"))
        );

        $smma9 = $this->trader->smma($closesMod, config("trader{$interval}m.smma9_period"));
        $smma21 = $this->trader->smma($closesMod, config("trader{$interval}m.smma21_period"));
        $smma50 = $this->trader->smma($closesMod, config("trader{$interval}m.smma50_period"));
        $smma200 = $this->trader->smma($closesMod, config("trader{$interval}m.smma200_period"));

        $ema9 = $this->trader->ema($closesMod, config("trader{$interval}m.ema9_period"));
        $ema21 = $this->trader->ema($closesMod, config("trader{$interval}m.ema21_period"));

        $rsi = $this->trader->rsi($closesMod, config("trader{$interval}m.rsi_period"));
        $stoch = $this->trader->stoch(
            $highsMod,
            $lowsMod,
            $closesMod,
            config("trader{$interval}m.stoch_fastk_period"),
            config("trader{$interval}m.stoch_slowk_period"),
            config("trader{$interval}m.stoch_slowk_ma_type"),
            config("trader{$interval}m.stoch_slowd_period"),
            config("trader{$interval}m.stoch_slowd_ma_type")
        );

        $macd  = $this->trader->macd(
            $closesMod,
            config("trader{$interval}m.macd_fast"),
            config("trader{$interval}m.macd_slow"),
            config("trader{$interval}m.macd_signal")
        );
        $adx    = $this->trader->adx($highs, $lows, $closes, config("trader{$interval}m.adx_period"));

        $roc10 = $this->trader->roc($closesMod, config("trader{$interval}m.roc_period"));
        $rocpVol = $this->trader->roc($vols, config("trader{$interval}m.vol_roc_period"));

        $ult  = $this->trader->ultosc(
            $highsMod,
            $lowsMod,
            $closesMod,
            config("trader{$interval}m.ult_1"),
            config("trader{$interval}m.ult_2"),
            config("trader{$interval}m.ult_3")
        );

        $bbands = $this->trader->bbands(
            $closesMod,
            config("trader{$interval}m.bbands_period"),
            config("trader{$interval}m.bbands_stddev"),
            config("trader{$interval}m.bbands_stddev")
        );
        // Bollinger Band width = (upper - lower) / middle
        $bbwidth = $this->trader->div(
            $this->trader->sub($bbands[2], $bbands[0]),
            $bbands[1]
        );

        $atr = $this->trader->atr($highsMod, $lowsMod, $closesMod, config("trader{$interval}m.atr_period"));
        $obv = $this->trader->obv($closesMod, $vols);

        // 3) Assemble per-bar indicator array
        $indicators = [];
        for ($i = 0; $i < $length; ++$i) {
            $indicators[] = [
                'open'           => $opens[$i],
                'high'           => $highs[$i],
                'low'            => $lows[$i],
                'close'          => $closes[$i],
                'volume'         => $vols[$i],

                'log_return'     => $logReturns[$i]     ?? 0.0,
                'price_rel_high' => $priceRelHigh[$i]  ?? 0.0,
                'price_rel_low'  => $priceRelLow[$i]   ?? 0.0,

                'smma9'          => $smma9[$i]         ?? 0.0,
                'smma21'          => $smma21[$i]         ?? 0.0,
                'smma50'          => $smma50[$i]         ?? 0.0,
                'smma200'         => $smma200[$i]        ?? 0.0,
                'ema9'          => $ema9[$i]         ?? 0.0,
                'ema21'          => $ema21[$i]         ?? 0.0,

                'rsi'            => $rsi[$i]         ?? 0.0,
                'stoch_k'        => $stoch[0][$i]      ?? 0.0,
                'stoch_d'        => $stoch[1][$i]      ?? 0.0,

                'macd'           => $macd[0][$i]       ?? 0.0,
                'macd_signal'    => $macd[1][$i]       ?? 0.0,
                'macd_hist'      => $macd[2][$i]       ?? 0.0,
                'adx'            => $adx[$i]           ?? 0.0,

                'roc10'          => $roc10[$i]         ?? 0.0,
                'vroc'           => $rocpVol[$i]       ?? 0.0,

                'ult_osc'        => $ult[$i]           ?? 0.0,

                'bb_lower'       => $bbands[0][$i]     ?? 0.0,
                'bb_middle'      => $bbands[1][$i]     ?? 0.0,
                'bb_upper'       => $bbands[2][$i]     ?? 0.0,
                'bb_width'       => $bbwidth[$i]       ?? 0.0,

                'atr'            => $atr[$i]           ?? 0.0,
                'obv'            => $obv[$i]           ?? 0.0,
            ];
        }


        // 4) Free up large temporaries
        unset(
            $opens,
            $highs,
            $lows,
            $closes,
            $vols,
            $opensMod,
            $highsMod,
            $lowsMod,
            $closesMod,
            $logReturns,
            $priceRelHigh,
            $priceRelLow,
            $smma9,
            $smma21,
            $smma50,
            $smma200,
            $ema9,
            $ema21,
            $rsi,
            $stoch,
            $macd,
            $adx,
            $roc10,
            $rocpVol,
            $ult,
            $bbands,
            $bbwidth,
            $atr,
            $obv
        );

        return $indicators;
    }

    /**
     * Compute log returns: ln(price_t / price_{t-n})
     */
    private function seriesLogReturns(array $series, int $period): array
    {
        $length = count($series);
        $res = array_fill(0, $length, 0.0);
        for ($i = $period; $i < $length; ++$i) {
            $prev = $series[$i - $period];
            $res[$i] = $prev > 0
                ? log($series[$i] / $prev)
                : 0.0;
        }
        return $res;
    }


    /**
     * Build a series of Heikin-Ashi candles from your indicator snapshots.
     *
     * @param array<int, array<string, mixed>> $ohlc  Each element must have at least
     *        ['open'=>float,'high'=>float,'low'=>float,'close'=>float].
     * @return array<int, array{open:float,high:float,low:float,close:float}>
     */
    private function buildHeikinAshi(array $ohlc): array
    {
        $ha = [];

        foreach ($ohlc as $i => $candle) {
            // TradingView format: ['y' => [open, high, low, close, …]]
            $o = $candle['y'][0];
            $h = $candle['y'][1];
            $l = $candle['y'][2];
            $c = $candle['y'][3];

            if ($i === 0) {
                $haOpen  = ($o + $c) / 2;
                $haClose = ($o + $h + $l + $c) / 4;
            } else {
                $prev    = $ha[$i - 1];
                $haOpen  = ($prev['open']  + $prev['close']) / 2;
                $haClose = ($o + $h + $l + $c)       / 4;
            }

            $ha[] = [
                'open'  => $haOpen,
                'high'  => max($h, $haOpen, $haClose),
                'low'   => min($l, $haOpen, $haClose),
                'close' => $haClose,
                // optional: preserve timestamp if you need it downstream
                'time'  => $candle['x'],
            ];
        }

        return $ha;
    }


    private function buildIndicators(array $ohlc): array
    {
        $length = count($ohlc);

        // 1) Preallocate and extract raw OHLCV arrays
        $opens  = $highs  = $lows  = $closes = $vols = array_fill(0, $length, 0.0);
        for ($i = 0; $i < $length; ++$i) {
            [$o, $h, $l, $c, $v] = $ohlc[$i]['y'];
            $opens[$i]  = (float) $o;
            $highs[$i]  = (float) $h;
            $lows[$i]   = (float) $l;
            $closes[$i] = (float) $c;
            $vols[$i]   = (int)   $v;
        }

        // 2) Compute your indicators once on the full series
        $smma50  = $this->trader->smma($closes, config('trader.smma50_period'));
        $smma200 = $this->trader->smma($closes, config('trader.smma200_period'));
        $ema20  = $this->trader->ema($closes, config('trader.ema20_period'));
        $bbands = $this->trader->bbands(
            $closes,
            config('trader.bbands_period'),
            config('trader.bbands_stddev'),
            config('trader.bbands_stddev')
        );
        $rsi    = $this->trader->rsi($closes, config('trader.rsi_period'));
        $macd   = $this->trader->macd(
            $closes,
            config('trader.macd_fast'),
            config('trader.macd_slow'),
            config('trader.macd_signal')
        );
        $adx    = $this->trader->adx($highs, $lows, $closes, config('trader.adx_period'));
        $stoch  = $this->trader->stoch(
            $highs,
            $lows,
            $closes,
            config('trader.stoch_fastk_period'),
            config('trader.stoch_slowk_period'),
            config('trader.stoch_slowk_ma_type'),
            config('trader.stoch_slowd_period'),
            config('trader.stoch_slowd_ma_type')
        );
        $obv    = $this->trader->obv($closes, $vols);
        $atr    = $this->trader->atr($highs, $lows, $closes, config('trader.atr_period'));

        // 3) Build the per-bar indicator array
        $indicators = [];
        for ($i = 0; $i < $length; ++$i) {
            $indicators[] = [
                'open'           => $opens[$i],
                'high'           => $highs[$i],
                'low'            => $lows[$i],
                'close'          => $closes[$i],
                'volume'         => $vols[$i],
                'smma50'          => $smma50[$i]  ?? 0.0,
                'smma200'         => $smma200[$i] ?? 0.0,
                'ema20'          => $ema20[$i]  ?? 0.0,
                'bbands_lower'   => $bbands[0][$i] ?? 0.0,
                'bbands_middle'  => $bbands[1][$i] ?? 0.0,
                'bbands_upper'   => $bbands[2][$i] ?? 0.0,
                'rsi'            => $rsi[$i]    ?? 0.0,
                'macd'           => $macd[0][$i] ?? 0.0,
                'macd_signal'    => $macd[1][$i] ?? 0.0,
                'macd_hist'      => $macd[2][$i] ?? 0.0,
                'adx'            => $adx[$i]    ?? 0.0,
                'stoch_k'        => $stoch[0][$i] ?? 0.0,
                'stoch_d'        => $stoch[1][$i] ?? 0.0,
                'obv'            => $obv[$i]    ?? 0.0,
                'atr'            => $atr[$i]    ?? 0.0,
            ];
        }

        return $indicators;
    }

    private function normalizeInterval(mixed $raw, int $default): int
    {
        if (empty($raw)) {
            return $default;
        }
        if (is_numeric($raw)) {
            return (int) $raw;
        }
        return OHLCIndicators::minutes(strtoupper((string) $raw));
    }

    private function fetchOhlc(string $pair, int $interval): array
    {
        $pair = AssetPair::getWsNameOrPairName($pair);

        try {
            $raw = $this->marketData->ohlc($pair, $interval);
            $this->upsertNewTicks($pair, $interval, $raw);
            Log::info("Inserted new ticks for {$pair}@{$interval}m");

            // Fetch back what’s in the DB, then structure it
            $ohlcvData = OhlcvData::fetchOHLCData($pair, $interval);

            return KrakenStructure::ohlcData($ohlcvData->toArray());
        } catch (\Throwable $e) {
            //I want you to separate this command, one command to train all the models and save them under pair_indicator.rbx and the other to predict 
            // Log the exception and return an empty array (or you could rethrow)
            Log::error("Error in fetchOhlc({$pair}, {$interval}): " . $e->getMessage(), [
                'pair'     => $pair,
                'interval' => $interval,
                'exception' => $e,
            ]);

            return [];
        }
    }
}
