<?php
declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\SignalLabeler;

class TradeParamGenerator
{
    protected int $timeframe;

    /**
     * ATR multiplier & explanation config per timeframe (minutes).
     *
     * sl = stop-loss multiplier (price ± sl×ATR)
     * tp = take-profit multiplier (price ± tp×ATR)
     * exp = human-readable explanation
     */
    protected static array $config = [

        // 1-minute scalps
        1 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'Micro trend; 1m ATR sizing.'],
            SignalLabeler::UPTREND_START    => ['sl'=>0.4, 'tp'=>0.8, 'exp'=>'1m early trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.3, 'tp'=>0.6, 'exp'=>'1m established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.3, 'tp'=>0.0,'exp'=>'1m trend end; exit.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'Micro reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>0.4, 'tp'=>0.8, 'exp'=>'1m reversal start.'],
            SignalLabeler::BREAKOUT         => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'1m breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'1m breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>0.3, 'tp'=>0.6, 'exp'=>'1m momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>0.6, 'tp'=>0.3, 'exp'=>'1m momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>0.2, 'tp'=>0.4, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>0.4, 'tp'=>0.8, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>0.4, 'tp'=>0.2, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>0.8, 'tp'=>0.4, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],

        // 5-minute scalps
        5 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>0.6, 'tp'=>1.2, 'exp'=>'Strong 5m uptrend.'],
            SignalLabeler::UPTREND_START    => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'Early 5m trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.4, 'tp'=>0.8, 'exp'=>'5m established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.4, 'tp'=>0.0,'exp'=>'5m trend end.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>0.6, 'tp'=>1.2, 'exp'=>'Strong 5m reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'5m reversal start.'],
            SignalLabeler::BREAKOUT         => ['sl'=>0.6, 'tp'=>1.2, 'exp'=>'5m breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>0.6, 'tp'=>1.2, 'exp'=>'5m breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>0.4, 'tp'=>0.8, 'exp'=>'5m momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>0.8, 'tp'=>0.4, 'exp'=>'5m momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>0.3, 'tp'=>0.6, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>0.6, 'tp'=>0.3, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>1.0, 'tp'=>0.5, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],

        // 15-minute swings
        15 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>0.7, 'tp'=>1.4, 'exp'=>'Strong 15m uptrend.'],
            SignalLabeler::UPTREND_START    => ['sl'=>0.6, 'tp'=>1.2, 'exp'=>'Early 15m trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'15m established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.5, 'tp'=>0.0,'exp'=>'15m trend end.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>0.7, 'tp'=>1.4, 'exp'=>'Strong 15m reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>0.6, 'tp'=>1.2, 'exp'=>'15m reversal start.'],
            SignalLabeler::BREAKOUT         => ['sl'=>0.7, 'tp'=>1.4, 'exp'=>'15m breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>0.7, 'tp'=>1.4, 'exp'=>'15m breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'15m momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>1.0, 'tp'=>0.5, 'exp'=>'15m momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>0.4, 'tp'=>0.8, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>0.7, 'tp'=>1.4, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>0.8, 'tp'=>0.4, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>1.2, 'tp'=>0.6, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],

        // 30-minute framework
        30 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>1.0, 'tp'=>1.5, 'exp'=>'Strong 30m uptrend.'],
            SignalLabeler::UPTREND_START    => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'Early 30m trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'30m established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.5, 'tp'=>0.0,'exp'=>'30m trend end; tighten stops.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>1.0, 'tp'=>1.5, 'exp'=>'Strong 30m reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>0.5, 'tp'=>0.0,'exp'=>'30m reversal forming.'],
            SignalLabeler::BREAKOUT         => ['sl'=>0.5, 'tp'=>1.5, 'exp'=>'30m breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>0.5, 'tp'=>1.5, 'exp'=>'30m breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>0.5, 'tp'=>1.0, 'exp'=>'30m momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>1.0, 'tp'=>0.5, 'exp'=>'30m momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>0.7, 'tp'=>1.0, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>1.0, 'tp'=>1.5, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>1.0, 'tp'=>0.7, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>1.5, 'tp'=>1.0, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],

        // 1-hour bars
        60 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'Strong 1h uptrend.'],
            SignalLabeler::UPTREND_START    => ['sl'=>0.75,'tp'=>1.5, 'exp'=>'Early 1h trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'1h established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.0,'tp'=>0.0,'exp'=>'1h trend end; tighten.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'Strong 1h reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>0.75,'tp'=>0.0,'exp'=>'1h reversal forming.'],
            SignalLabeler::BREAKOUT         => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'1h breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'1h breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>1.0, 'tp'=>1.5, 'exp'=>'1h momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>1.5, 'tp'=>1.0, 'exp'=>'1h momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>1.2, 'tp'=>1.5, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>1.5, 'tp'=>2.0, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>1.5, 'tp'=>1.2, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>2.0, 'tp'=>1.5, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],

        // 4-hour bars
        240 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'Strong 4h uptrend.'],
            SignalLabeler::UPTREND_START    => ['sl'=>0.75,'tp'=>1.5, 'exp'=>'Early 4h trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'4h established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.0,'tp'=>0.0,'exp'=>'4h trend end; tighten.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'Strong 4h reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>0.75,'tp'=>0.0,'exp'=>'4h reversal forming.'],
            SignalLabeler::BREAKOUT         => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'4h breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'4h breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>1.0, 'tp'=>1.5, 'exp'=>'4h momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>1.5, 'tp'=>1.0, 'exp'=>'4h momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>1.2, 'tp'=>1.8, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>1.5, 'tp'=>2.5, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>1.5, 'tp'=>1.2, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>2.5, 'tp'=>1.5, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],

        // Daily bars
        1440 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>1.5, 'tp'=>3.0, 'exp'=>'Strong daily uptrend.'],
            SignalLabeler::UPTREND_START    => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'Early daily trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Daily established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Daily trend end; tighten.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>1.5, 'tp'=>3.0, 'exp'=>'Strong daily reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>1.0, 'tp'=>0.0,'exp'=>'Daily reversal forming.'],
            SignalLabeler::BREAKOUT         => ['sl'=>1.5, 'tp'=>3.0, 'exp'=>'Daily breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>1.5, 'tp'=>3.0, 'exp'=>'Daily breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>1.0, 'tp'=>2.0, 'exp'=>'Daily momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>2.0, 'tp'=>1.0, 'exp'=>'Daily momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>1.2, 'tp'=>2.0, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>1.5, 'tp'=>3.0, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>2.0, 'tp'=>1.2, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>3.0, 'tp'=>1.5, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],

        // Weekly bars (10080 min)
        10080 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>2.0, 'tp'=>4.0, 'exp'=>'Strong weekly uptrend.'],
            SignalLabeler::UPTREND_START    => ['sl'=>1.5, 'tp'=>3.0, 'exp'=>'Early weekly trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Weekly established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Weekly trend end; tighten.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>2.0, 'tp'=>4.0, 'exp'=>'Strong weekly reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>1.5, 'tp'=>0.0,'exp'=>'Weekly reversal forming.'],
            SignalLabeler::BREAKOUT         => ['sl'=>2.0, 'tp'=>4.0, 'exp'=>'Weekly breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>2.0, 'tp'=>4.0, 'exp'=>'Weekly breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>1.5, 'tp'=>3.0, 'exp'=>'Weekly momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>3.0, 'tp'=>1.5, 'exp'=>'Weekly momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>2.5, 'tp'=>3.0, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>3.0, 'tp'=>4.0, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>3.0, 'tp'=>2.5, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>4.0, 'tp'=>3.0, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],

        // 3-week bars (21600 min)
        21600 => [
            SignalLabeler::STRONG_UPTREND   => ['sl'=>3.0, 'tp'=>6.0, 'exp'=>'Strong 3-week uptrend.'],
            SignalLabeler::UPTREND_START    => ['sl'=>2.5, 'tp'=>5.0, 'exp'=>'Early 3-week trend.'],
            SignalLabeler::UPTREND          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'3-week established trend.'],
            SignalLabeler::UPTREND_END      => ['sl'=>0.0,'tp'=>0.0,'exp'=>'3-week trend end; tighten.'],
            SignalLabeler::STRONG_REVERSAL  => ['sl'=>3.0, 'tp'=>6.0, 'exp'=>'Strong 3-week reversal.'],
            SignalLabeler::REVERSAL         => ['sl'=>2.5, 'tp'=>0.0,'exp'=>'3-week reversal forming.'],
            SignalLabeler::BREAKOUT         => ['sl'=>3.0, 'tp'=>6.0, 'exp'=>'3-week breakout.'],
            SignalLabeler::BREAKDOWN        => ['sl'=>3.0, 'tp'=>6.0, 'exp'=>'3-week breakdown.'],
            SignalLabeler::MOMENTUM_UP      => ['sl'=>2.0, 'tp'=>4.0, 'exp'=>'3-week momentum up.'],
            SignalLabeler::MOMENTUM_DOWN    => ['sl'=>4.0, 'tp'=>2.0, 'exp'=>'3-week momentum down.'],
            SignalLabeler::PROFIT_UP_1      => ['sl'=>2.5, 'tp'=>5.0, 'exp'=>'Target +1% profit.'],
            SignalLabeler::PROFIT_UP_3      => ['sl'=>3.0, 'tp'=>6.0, 'exp'=>'Target +3% profit.'],
            SignalLabeler::PROFIT_DOWN_1    => ['sl'=>5.0, 'tp'=>2.5, 'exp'=>'Target −1% profit.'],
            SignalLabeler::PROFIT_DOWN_3    => ['sl'=>6.0, 'tp'=>3.0, 'exp'=>'Target −3% profit.'],
            SignalLabeler::CONSOLIDATION    => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Range-bound; hold.'],
            SignalLabeler::CHOPPY           => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Choppy; avoid.'],
            SignalLabeler::NEUTRAL          => ['sl'=>0.0,'tp'=>0.0,'exp'=>'Neutral; no trade.'],
        ],
    ];

    public function __construct(int $timeframe)
    {
        if (! isset(self::$config[$timeframe])) {
            throw new \InvalidArgumentException("Unsupported timeframe: {$timeframe}m");
        }
        $this->timeframe = $timeframe;
    }

    /**
     * @return array{0: float|null,1: float|null,2: float|null,3: string}
     *   [entry, stopLoss, takeProfit, explanation]
     */
    public function generate(
        string $signal,
        float  $price,
        float  $atr,
        array  $levels,
        array  $last
    ): array {
        $entry = $price;
        $sl    = 0.0;
        $tp    = 0.0;
        $exp   = '';

        $table = self::$config[$this->timeframe];

        if (isset($table[$signal])) {
            ['sl' => $slm, 'tp' => $tpm, 'exp' => $exp] = $table[$signal];

            $sl = $slm !== 0.0
                ? $price - $slm * $atr
                : ($levels['support'][0] ?? $price * 0.995);

            $tp = $tpm !== 0.0
                ? $price + $tpm * $atr
                : ($levels['resistance'][0] ?? $price * 1.005);
        }

        // Fallback sizing if ATR is zero or missing
        if ($atr <= 0.0) {
            $sl  = $sl  ?? $price * 0.997;
            $tp  = $tp  ?? $price * 1.003;
            $exp .= ' (fallback sizing)';
        }

        return [$entry, $sl, $tp, $exp];
    }
}
