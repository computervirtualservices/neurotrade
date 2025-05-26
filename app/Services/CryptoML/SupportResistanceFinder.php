<?php
declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\Contracts\SupportResistanceFinderInterface;

/**
 * Detects swing-based support & resistance levels from recent OHLC data.
 */
final class SupportResistanceFinder implements SupportResistanceFinderInterface
{
    public function __construct(
        private readonly int $lookback = 30
    ) {}

    /**
     * Return nearest support & resistance levels (closest-first).
     *
     * @param array<int,array<string,mixed>> $indicators  Chronological indicator rows with 'high'/'low' keys
     * @param float                          $price       Current price
     * @return array{support: float[], resistance: float[]}
     */
    public function levels(array $indicators, float $price): array
    {
        $slice = array_slice($indicators, -$this->lookback);
        $highs = array_column($slice, 'high');
        $lows  = array_column($slice, 'low');

        if (empty($highs) || empty($lows)) {
            return $this->defaultLevels($price);
        }

        return [
            'support'    => $this->swingLows($lows, $price),
            'resistance' => $this->swingHighs($highs, $price),
        ];
    }

    /**
     * Fallback if no valid swings are found.
     */
    private function defaultLevels(float $price): array
    {
        return [
            'support'    => [$price * 0.95, $price * 0.90],
            'resistance' => [$price * 1.05, $price * 1.10],
        ];
    }

    /**
     * Find up to 3 swing-lows below the price, sorted by proximity.
     *
     * @param float[] $lows
     * @return float[]
     */
    private function swingLows(array $lows, float $price): array
    {
        $candidates = [];
        $n = count($lows);

        for ($i = 1; $i < $n - 1; $i++) {
            if (
                $lows[$i] < $lows[$i - 1]
                && $lows[$i] < $lows[$i + 1]
                && $lows[$i] < $price
            ) {
                $candidates[] = $lows[$i];
            }
        }

        return $this->closestOrDefault($candidates, $price, 3, 0.95, 0.90);
    }

    /**
     * Find up to 3 swing-highs above the price, sorted by proximity.
     *
     * @param float[] $highs
     * @return float[]
     */
    private function swingHighs(array $highs, float $price): array
    {
        $candidates = [];
        $n = count($highs);

        for ($i = 1; $i < $n - 1; $i++) {
            if (
                $highs[$i] > $highs[$i - 1]
                && $highs[$i] > $highs[$i + 1]
                && $highs[$i] > $price
            ) {
                $candidates[] = $highs[$i];
            }
        }

        return $this->closestOrDefault($candidates, $price, 3, 1.05, 1.10);
    }

    /**
     * Sort candidates by absolute distance to $price, take $limit;
     * or fall back to two default multipliers if none found.
     *
     * @param float[] $candidates
     * @param float   $price
     * @param int     $limit
     * @param float   $fallbackA
     * @param float   $fallbackB
     * @return float[]
     */
    private function closestOrDefault(
        array $candidates,
        float $price,
        int $limit,
        float $fallbackA,
        float $fallbackB
    ): array {
        if (empty($candidates)) {
            return [$price * $fallbackA, $price * $fallbackB];
        }

        usort($candidates, fn(float $a, float $b): int => abs($price - $a) <=> abs($price - $b));
        return array_slice($candidates, 0, $limit);
    }
}
