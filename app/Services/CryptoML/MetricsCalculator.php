<?php
declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\Contracts\MetricsCalculatorInterface;

/**
 * Implements MetricsCalculatorInterface to provide volatility,
 * volume, breakout, and breakdown calculations from OHLC data.
 */
final class MetricsCalculator implements MetricsCalculatorInterface
{
    public function __construct(
        private readonly int $volatilityLookback   = 30,
        private readonly int $volumeLookback       = 30,
        private readonly int $breakoutLookback     = 30,
    ) {}

    /**
     * Average ATR-like volatility over past indicator snapshots.
     * @param array<int,array<string,mixed>> $indicators
     */
    public function averageVolatility(array $indicators, int $period): float
    {
        $slice = array_slice($indicators, -$period);
        $sum   = 0.0;
        $count = count($slice);

        if ($count === 0) {
            return 0.0;
        }

        foreach ($slice as $row) {
            // Prefer ATR if available, else high-low diff
            $atr = $row['atr'] ?? ($row['high'] - $row['low']);
            $sum += (float) $atr;
        }

        return $sum / $count;
    }

    /**
     * {@inheritDoc}
     */
    public function averageVolume(array $indicators, int $period): float
    {
        $slice   = array_slice($indicators, -$period);
        $volumes = array_column($slice, 'volume');
        
        return empty($volumes)
            ? 0.0
            : array_sum($volumes) / count($volumes);
    }

    /**
     * Compute volatility (%) over future OHLC window: avg of (high-low)/open*100
     * @param array<int,array<string,mixed>> $ohlc
     */
    public function volatilityFromCandles(array $ohlc, int $startIndex, int $length): float
    {
        $slice = array_slice($ohlc, $startIndex, $length);
        $sum   = 0.0;
        $count = count($slice);

        if ($count === 0) {
            return 0.0;
        }

        foreach ($slice as $candle) {
            [$open, $high, $low] = [
                (float) ($candle['y'][0] ?? 0),
                (float) ($candle['y'][1] ?? 0),
                (float) ($candle['y'][2] ?? 0),
            ];
            if ($open > 0.0) {
                $sum += (($high - $low) / $open) * 100.0;
            }
        }

        return $sum / $count;
    }

    /**
     * Determine if volume at index is unusually high vs past average.
     * @param array<int,array<string,mixed>> $ohlc
     */
    public function isHighVolume(array $ohlc, int $index): bool
    {
        if (! isset($ohlc[$index]['y'][4])) {
            return false;
        }
        $volumes = [];
        $start   = max(0, $index - $this->volumeLookback);
        for ($i = $start; $i < $index; $i++) {
            $volumes[] = (float) ($ohlc[$i]['y'][4] ?? 0);
        }
        $avg = count($volumes) ? array_sum($volumes) / count($volumes) : 0.0;
        $curr = (float) $ohlc[$index]['y'][4];
        return $avg > 0.0 && $curr > $avg * 1.5;
    }

    /**
     * True if current high > max high of lookback candles.
     */
    public function detectBreakout(array $ohlc, int $index): bool
    {
        if (! isset($ohlc[$index]['y'][1])) {
            return false;
        }
        $start = max(0, $index - $this->breakoutLookback);
        $maxHigh = 0.0;
        for ($i = $start; $i < $index; $i++) {
            $high = (float) ($ohlc[$i]['y'][1] ?? 0);
            $maxHigh = max($maxHigh, $high);
        }
        $currHigh = (float) $ohlc[$index]['y'][1];
        return $currHigh > $maxHigh;
    }

    /**
     * True if current low < min low of lookback candles.
     */
    public function detectBreakdown(array $ohlc, int $index): bool
    {
        if (! isset($ohlc[$index]['y'][2])) {
            return false;
        }
        $start = max(0, $index - $this->breakoutLookback);
        $minLow = PHP_FLOAT_MAX;
        for ($i = $start; $i < $index; $i++) {
            $low = (float) ($ohlc[$i]['y'][2] ?? PHP_FLOAT_MAX);
            $minLow = min($minLow, $low);
        }
        $currLow = (float) $ohlc[$index]['y'][2];
        return $currLow < $minLow;
    }
}
