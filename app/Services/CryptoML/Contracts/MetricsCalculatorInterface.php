<?php
declare(strict_types=1);

namespace App\Services\CryptoML\Contracts;

/**
 * Calculates volatility, breakout/breakdown, and volume metrics.
 */
interface MetricsCalculatorInterface
{
    /**
     * Average volatility over a past look-back period.
     *
     * @param array<int,array<string,mixed>> $indicators  Indicator snapshots
     * @param int                            $period      Look-back length in candles
     */
    public function averageVolatility(array $indicators, int $period): float;
    
    /**
     * Calculate average volume over a period
     * 
     * @param array $indicators Array of indicators containing volume data
     * @param int $period Number of periods to average
     * @return float The average volume
     */
    public function averageVolume(array $indicators, int $period): float;

    /**
     * Volatility from raw OHLC data in a future window.
     *
     * @param array<int,array<string,mixed>> $ohlc        Raw OHLC rows
     * @param int                            $startIndex  Starting candle index
     * @param int                            $length      Number of candles
     */
    public function volatilityFromCandles(array $ohlc, int $startIndex, int $length): float;

    /**
     * Detects whether volume at the given index is unusually high.
     *
     * @param array<int,array<string,mixed>> $ohlc   Raw OHLC rows
     * @param int                            $index  Candle index
     */
    public function isHighVolume(array $ohlc, int $index): bool;

    /**
     * Detects a breakout event in the OHLC history.
     *
     * @param array<int,array<string,mixed>> $ohlc   Raw OHLC rows
     * @param int                            $index  Candle index
     */
    public function detectBreakout(array $ohlc, int $index): bool;

    /**
     * Detects a breakdown event in the OHLC history.
     *
     * @param array<int,array<string,mixed>> $ohlc   Raw OHLC rows
     * @param int                            $index  Candle index
     */
    public function detectBreakdown(array $ohlc, int $index): bool;
}
