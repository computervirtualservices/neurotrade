<?php
declare(strict_types=1);

namespace App\Services\CryptoML\Contracts;

use Rubix\ML\Datasets\Labeled;

/**
 * Finds swing-based support and resistance levels.
 */
interface SupportResistanceFinderInterface
{
    /**
     * @param array<int,array<string,mixed>> $indicators    Chronological OHLC rows
     * @param float                          $currentPrice  Last traded price
     * @return array{support:float[],resistance:float[]}
     */
    public function levels(array $indicators, float $currentPrice): array;
}