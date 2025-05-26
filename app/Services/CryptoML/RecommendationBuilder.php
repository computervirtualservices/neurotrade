<?php

declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\Contracts\RecommendationBuilderInterface;
use App\Services\CryptoML\Contracts\SupportResistanceFinderInterface;
use App\Services\CryptoML\SignalLabeler;

/**
 * Build actionable trading recommendations from model signals and market context.
 */
final class RecommendationBuilder implements RecommendationBuilderInterface
{
    private const CONF_HIGH   = 0.80;
    private const CONF_MEDIUM = 0.65;

    private const ACTION_MAP = [
    SignalLabeler::STRONG_UPTREND   => ['BUY',      'STRONG'],
    SignalLabeler::UPTREND_START    => ['BUY',      'MEDIUM'],
    SignalLabeler::UPTREND          => ['BUY',      'MEDIUM'],

    // PROFIT_* means “expect another X% move in that direction” → BUY up, SELL down
    SignalLabeler::PROFIT_UP_3      => ['BUY',      'STRONG'],
    SignalLabeler::PROFIT_UP_1      => ['BUY',      'MEDIUM'],

    SignalLabeler::UPTREND_END      => ['SELL',     'MEDIUM'],      // trend exhaustion
    SignalLabeler::STRONG_REVERSAL  => ['SELL',     'STRONG'],
    SignalLabeler::REVERSAL         => ['SELL',     'MEDIUM'],

    SignalLabeler::PROFIT_DOWN_1    => ['SELL',     'MEDIUM'],
    SignalLabeler::PROFIT_DOWN_3    => ['SELL',     'STRONG'],

    SignalLabeler::BREAKOUT         => ['BUY',      'STRONG'],
    SignalLabeler::BREAKDOWN        => ['SELL',     'STRONG'],

    SignalLabeler::MOMENTUM_UP      => ['BUY',      'LOW'],
    SignalLabeler::MOMENTUM_DOWN    => ['SELL',     'LOW'],

    SignalLabeler::CONSOLIDATION    => ['HOLD',     'NEUTRAL'],
    SignalLabeler::CHOPPY           => ['AVOID',    'NEUTRAL'],
    SignalLabeler::NEUTRAL          => ['HOLD',     'NEUTRAL'],
];

    public function __construct(
        private readonly SupportResistanceFinderInterface $srf,
    ) {}

    /**
     * Return all signals that map to a given action (BUY, SELL, etc).
     */
    public function signalsForAction(string $action): array
    {
        return array_keys(array_filter(
            self::ACTION_MAP,
            fn(array $cfg) => $cfg[0] === strtoupper($action)
        ));
    }

    /**
     * Build recommendation payload.
     *
     * @param array<int,array<string,mixed>> $indicators
     * @return array<string,mixed>
     */
    public function build(
        string $signal,
        float  $confidence,
        array  $indicators,
        float  $price,
        SupportResistanceFinderInterface $srFinder,
        int    $interval,
    ): array {
        [$action, $strength]     = $this->mapActionStrength($signal);
        [$confidencePct, $level] = $this->computeConfidenceLevel($confidence);

        $last       = end($indicators) ?: [];
        $keyInd     = $this->extractKeyIndicators($last);
        $levels     = $this->srf->levels($indicators, $price);
        [$entry, $sl, $tp, $exp] = $this->generateTradeParams($signal, $price, $levels, $last, $interval);

        if ($this->hasConflictingIndicators($keyInd)) {
            $level        = 'LOW';
            $confidencePct = min($confidencePct, 75.0);
            $exp         .= ' Conflicting indicators.';
        }

        return [
            'action'               => $action,
            'strength'             => $strength,
            'confidence'           => round($confidencePct, 2),
            'confidence_level'     => $level,
            'explanation'          => $exp,
            'suggested_entry'      => $entry,
            'suggested_stop_loss'  => $sl,
            'suggested_take_profit' => $tp,
            'support_levels'       => $levels['support'],
            'resistance_levels'    => $levels['resistance'],
            'key_indicators'       => $keyInd,
        ];
    }

    private function mapActionStrength(string $signal): array
    {
        return self::ACTION_MAP[$signal] ?? ['HOLD', 'NEUTRAL'];
    }

    private function computeConfidenceLevel(float $conf): array
    {
        $pct   = $conf * 100.0;
        $level = $conf >= self::CONF_HIGH   ? 'HIGH'
            : ($conf >= self::CONF_MEDIUM ? 'MEDIUM' : 'LOW');
        return [$pct, $level];
    }

    private function extractKeyIndicators(array $last): array
    {
        $rsi    = $last['rsi'] ?? null;
        $macd   = $last['macd'] ?? null;
        $sig    = $last['macd_signal'] ?? null;
        $adx    = $last['adx'] ?? null;

        return [
            'rsi'  => ['value' => $rsi,  'note' => $this->interpretRsi($rsi)],
            'macd' => [
                'value' => $macd,
                'signal' => $sig,
                'note'  => $macd !== null && $sig !== null
                    ? ($macd > $sig ? 'Bullish' : 'Bearish')
                    : 'Unknown'
            ],
            'adx'  => ['value' => $adx,  'note' => $this->interpretAdx($adx)],
        ];
    }

    /**
     * Generate entry, stop-loss, take-profit, and explanation per signal.
     */
    private function generateTradeParams(
        string $signal,
        float  $price,
        array  $levels,
        array  $last,
        int   $interval
    ): array {
        // Extract ATR (will be 0.0 if missing)
        $atr = $last['atr'] ?? 0.0;

        // Build a TradeParamGenerator for the current interval
        $generator = new TradeParamGenerator($interval);

        // Delegate all sizing and messaging to the generator
        [$entry, $sl, $tp, $exp] = $generator->generate(
            $signal,
            $price,
            $atr,
            $levels,
            $last
        );

        return [$entry, $sl, $tp, $exp];
    }

    private function interpretRsi(?float $rsi): string
    {
        return match (true) {
            $rsi === null => 'Unknown',
            $rsi > 70     => 'Overbought',
            $rsi < 30     => 'Oversold',
            $rsi > 60     => 'Bullish',
            $rsi < 40     => 'Bearish',
            default       => 'Neutral',
        };
    }

    private function interpretAdx(?float $adx): string
    {
        return match (true) {
            $adx === null => 'Unknown',
            $adx > 50     => 'Strong Trend',
            $adx > 25     => 'Moderate Trend',
            $adx < 20     => 'Weak/No Trend',
            default       => 'Developing Trend',
        };
    }

    private function hasConflictingIndicators(array $keyInd): bool
    {
        $adx = $keyInd['adx']['value'] ?? null;
        if ($adx !== null && $adx < 20.0) {
            return true;
        }

        $bull = $bear = 0;
        if (isset($keyInd['rsi']['value'])) {
            $rsi = $keyInd['rsi']['value'];
            $rsi > 60 ? $bull++ : ($rsi < 40 ? $bear++ : null);
        }
        if (isset($keyInd['macd']['value'], $keyInd['macd']['signal'])) {
            $macd = $keyInd['macd']['value'];
            $sig  = $keyInd['macd']['signal'];
            $macd > $sig ? $bull++ : ($macd < $sig ? $bear++ : null);
        }

        return $bull > 0 && $bear > 0;
    }
}
