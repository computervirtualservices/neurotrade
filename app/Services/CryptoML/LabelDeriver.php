<?php
declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\SignalLabeler;

class LabelDeriver
{
    private const LOOKAHEAD = 5;

    protected int $timeframe;

    /**
     * Threshold configuration per timeframe (minutes).
     */
    protected static array $config = [
        1     => [ // 1-minute
            'strong_pct'      => 1.0,  'strong_tc'  => 0.9,
            'start_pct'       => 0.8,  'start_pct1' => 0.4,
            'established_pct' => 0.5,  'established_tc' => 0.6,
            'end_pullback'    => -0.2, 'end_rsi'    => 80,
            'profit_3'        => 0.6,  'profit_1'   => 0.3,
            'cons_pct'        => 0.2,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 0.5,  'choppy_tc'  => 0.2,
        ],
        5     => [ // 5-minute
            'strong_pct'      => 1.5,  'strong_tc'  => 0.85,
            'start_pct'       => 1.0,  'start_pct1' => 0.5,
            'established_pct' => 1.0,  'established_tc' => 0.6,
            'end_pullback'    => -0.3, 'end_rsi'    => 75,
            'profit_3'        => 1.0,  'profit_1'   => 0.5,
            'cons_pct'        => 0.3,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 1.0,  'choppy_tc'  => 0.3,
        ],
        15    => [ // 15-minute
            'strong_pct'      => 2.0,  'strong_tc'  => 0.8,
            'start_pct'       => 1.5,  'start_pct1' => 1.0,
            'established_pct' => 1.5,  'established_tc' => 0.6,
            'end_pullback'    => -0.5, 'end_rsi'    => 70,
            'profit_3'        => 1.5,  'profit_1'   => 0.75,
            'cons_pct'        => 0.5,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 1.5,  'choppy_tc'  => 0.3,
        ],
        30    => [ // 30-minute
            'strong_pct'      => 3.0,  'strong_tc'  => 0.8,
            'start_pct'       => 2.0,  'start_pct1' => 1.0,
            'established_pct' => 2.0,  'established_tc' => 0.6,
            'end_pullback'    => -0.5, 'end_rsi'    => 75,
            'profit_3'        => 3.0,  'profit_1'   => 1.0,
            'cons_pct'        => 1.0,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 1.5,  'choppy_tc'  => 0.3,
        ],
        60    => [ // 1-hour
            'strong_pct'      => 4.0,  'strong_tc'  => 0.8,
            'start_pct'       => 3.0,  'start_pct1' => 1.5,
            'established_pct' => 3.0,  'established_tc' => 0.6,
            'end_pullback'    => -0.75,'end_rsi'    => 70,
            'profit_3'        => 4.0,  'profit_1'   => 1.5,
            'cons_pct'        => 1.5,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 2.0,  'choppy_tc'  => 0.3,
        ],
        240   => [ // 4-hour
            'strong_pct'      => 6.0,  'strong_tc'  => 0.8,
            'start_pct'       => 4.0,  'start_pct1' => 2.0,
            'established_pct' => 2.0,  'established_tc' => 0.6,
            'end_pullback'    => -1.0, 'end_rsi'    => 75,
            'profit_3'        => 6.0,  'profit_1'   => 2.0,
            'cons_pct'        => 2.0,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 3.0,  'choppy_tc'  => 0.3,
        ],
        1440  => [ // daily
            'strong_pct'      => 10.0, 'strong_tc'  => 0.8,
            'start_pct'       => 6.0,  'start_pct1' => 3.0,
            'established_pct' => 2.0,  'established_tc' => 0.6,
            'end_pullback'    => -2.0, 'end_rsi'    => 75,
            'profit_3'        => 10.0, 'profit_1'   => 3.0,
            'cons_pct'        => 3.0,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 5.0,  'choppy_tc'  => 0.3,
        ],
        10080 => [ // weekly
            'strong_pct'      => 20.0, 'strong_tc'  => 0.8,
            'start_pct'       => 10.0, 'start_pct1' => 5.0,
            'established_pct' => 5.0,  'established_tc' => 0.6,
            'end_pullback'    => -5.0, 'end_rsi'    => 75,
            'profit_3'        => 20.0, 'profit_1'   => 5.0,
            'cons_pct'        => 5.0,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 8.0,  'choppy_tc'  => 0.3,
        ],
        21600 => [ // 3-week
            'strong_pct'      => 30.0, 'strong_tc'  => 0.8,
            'start_pct'       => 15.0, 'start_pct1' => 7.0,
            'established_pct' => 7.0,  'established_tc' => 0.6,
            'end_pullback'    => -7.0, 'end_rsi'    => 75,
            'profit_3'        => 30.0, 'profit_1'   => 7.0,
            'cons_pct'        => 7.0,  'cons_vol'   => 1.2,
            'choppy_pct1'     => 12.0, 'choppy_tc'  => 0.3,
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
     * Derives a SignalLabeler constant for bar index $i.
     *
     * @param  array<int,array{y:array{3:float}}> $ohlc
     * @param  array<int,array<string,mixed>>    $ind
     * @param  int                                $i
     * @param  float                              $avgVol
     * @return string
     */
    public function deriveLabel(
        array $ohlc,
        array $ind,
        int   $i,
        float $avgVol
    ): string {
        $cfg = self::$config[$this->timeframe];

        $c0 = $ohlc[$i]['y'][3];
        $c1 = $ohlc[$i + 1]['y'][3] ?? $c0;
        $cN = $ohlc[$i + self::LOOKAHEAD]['y'][3] ?? $c1;

        $chg1 = $this->pct($c1, $c0);
        $chgN = $this->pct($cN, $c0);
        $tc   = $this->trendConsistency($c0, $c1, $cN, $cN);

        $volFuture = (new MetricsCalculator())->volatilityFromCandles($ohlc, $i + 1, self::LOOKAHEAD);
        $highVol   = $volFuture > $avgVol * $cfg['cons_vol'];
        $highV     = (new MetricsCalculator())->isHighVolume($ohlc, $i);

        $last = $ind[$i]     ?? [];
        $prev = $ind[$i - 1] ?? $last;
        $momUp = $this->isMomentumUp($last, $prev);
        $momDn = $this->isMomentumDown($last, $prev);

        $breakOut  = (new MetricsCalculator())->detectBreakout($ohlc, $i);
        $breakDown = (new MetricsCalculator())->detectBreakdown($ohlc, $i);

        return match (true) {
            $breakOut  && $highVol                                => SignalLabeler::BREAKOUT,
            $breakDown && $highVol                                => SignalLabeler::BREAKDOWN,

            $chgN >= $cfg['strong_pct'] && $tc >= $cfg['strong_tc'] && $momUp
                                                                 => SignalLabeler::STRONG_UPTREND,
            $chgN <= -$cfg['strong_pct'] && $tc <= -$cfg['strong_tc'] && $momDn
                                                                 => SignalLabeler::STRONG_REVERSAL,

            $chgN >= $cfg['start_pct'] && $chg1 >= $cfg['start_pct1'] && $momUp
                                                                 => SignalLabeler::UPTREND_START,
            $chgN <= -$cfg['start_pct'] && $chg1 <= -$cfg['start_pct1'] && $momDn
                                                                 => SignalLabeler::REVERSAL,

            $chgN >= $cfg['established_pct'] && $tc >= $cfg['established_tc'] && ! $highVol
                                                                 => SignalLabeler::UPTREND,

            $chgN > 0 && $chg1 < $cfg['end_pullback'] && ($last['rsi'] ?? 0) > $cfg['end_rsi']
                                                                 => SignalLabeler::UPTREND_END,

            $momUp && $highV                                      => SignalLabeler::MOMENTUM_UP,
            $momDn && $highV                                      => SignalLabeler::MOMENTUM_DOWN,

            $chgN >= $cfg['profit_3']                             => SignalLabeler::PROFIT_UP_3,
            $chgN >= $cfg['profit_1']                             => SignalLabeler::PROFIT_UP_1,
            $chgN <= -$cfg['profit_3']                            => SignalLabeler::PROFIT_DOWN_3,
            $chgN <= -$cfg['profit_1']                            => SignalLabeler::PROFIT_DOWN_1,

            abs($chgN) < $cfg['cons_pct'] && $volFuture < $avgVol * $cfg['cons_vol']
                                                                 => SignalLabeler::CONSOLIDATION,
            abs($chg1) > $cfg['choppy_pct1'] && $highVol && abs($tc) < $cfg['choppy_tc']
                                                                 => SignalLabeler::CHOPPY,

            default                                               => SignalLabeler::NEUTRAL,
        };
    }

    private function pct(float $a, float $b): float
    {
        return $b !== 0.0 ? (($a - $b) / $b) * 100.0 : 0.0;
    }

    private function trendConsistency(float $c0, float $c1, float $c3, float $c5): float
    {
        $score  = 0.0;
        $parts  = [0.4, 0.3, 0.3];
        $deltas = [$c1 - $c0, $c3 - $c1, $c5 - $c3];

        foreach ($deltas as $idx => $delta) {
            $score += $delta > 0 ? $parts[$idx] : ($delta < 0 ? -$parts[$idx] : 0);
        }

        return array_sum($parts) > 0.0 ? $score / array_sum($parts) : 0.0;
    }

    private function isMomentumUp(array $last, array $prev): bool
    {
        return ($last['macd'] ?? 0) > ($last['macd_signal'] ?? 0)
            && ($last['macd_hist'] ?? 0) > ($prev['macd_hist'] ?? 0)
            && ($last['rsi'] ?? 0) > 50;
    }

    private function isMomentumDown(array $last, array $prev): bool
    {
        return ($last['macd'] ?? 0) < ($last['macd_signal'] ?? 0)
            && ($last['macd_hist'] ?? 0) < ($prev['macd_hist'] ?? 0)
            && ($last['rsi'] ?? 0) < 50;
    }
}
