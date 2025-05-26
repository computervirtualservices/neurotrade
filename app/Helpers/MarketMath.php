<?php

namespace App\Helpers;

final class MarketMath
{
    /**
     * Calculate VWAP from a single OHLC row
     *
     * @param array $row Format: [timestamp, open, high, low, close, volume, quoteVolume, tradeCount]
     * @return float|null
     */
    public static function calculateVWAP(array $row): ?float
    {
        if (count($row) < 6) {
            return null;
        }

        $high   = (float) $row[2];
        $low    = (float) $row[3];
        $close  = (float) $row[4];
        $volume = (float) $row[5];

        if ($volume <= 0.0) {
            return 0;
        }

        $typicalPrice = ($high + $low + $close) / 3.0;
        $vwap = $typicalPrice * $volume / $volume;

        return round($vwap, 6);
    }
}
