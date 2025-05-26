<?php

namespace App\Helpers;

final class KrakenStructure
{
    public static function ohlcData(array $raw): array
    {
        return $ohlcData = collect($raw)
            ->map(function ($tick) {
                return [
                    'x' => $tick[0] * 1000, // timestamp in milliseconds
                    'y' => [
                        (float) $tick[1], // open
                        (float) $tick[2], // high
                        (float) $tick[3], // low
                        (float) $tick[4], // close
                        (int) $tick[6],   // volume
                    ],
                ];
            })
            ->values() // reset array indexes
            ->toArray();
    }
}