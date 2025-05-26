<?php
declare(strict_types=1);

namespace App\Services\CryptoML\Contracts;

use Rubix\ML\Datasets\Labeled;

/**
 * Extracts a feature vector from indicator history and the current candle.
 */
interface FeatureExtractorInterface
{
    /**
     * Build a numeric feature array for the ML model.
     *
     * @param array<int,array<string,mixed>> $indicators       Chronological indicator snapshots
     * @param array{y:array{0:float,1:float,2:float,3:float,4:int}} $currentCandle  OHLCV data
     * @return array<float|int>
     */
    public function extract(array $indicators, array $currentCandle, int $i): array;
}