<?php
declare(strict_types=1);

namespace App\Services\CryptoML\Contracts;

use Rubix\ML\Datasets\Labeled;

/**
 * Generates supervised-learning labels from price/indicator history.
 */
interface SignalLabelerInterface
{
    /**
     * Create a Rubix Labeled dataset: features from FeatureExtractor,
     * labels derived by internal logic.
     *
     * @param array<int,array<string,mixed>> $ohlc        OHLCV history
     * @param array<int,array<string,mixed>> $indicators  Indicator snapshots
     * @return Labeled
     */
    public function makeDataset(array $ohlc, array $indicators, FeatureExtractorInterface $fx, int $timeframe, bool $useRegression = false): Labeled;

    /**
     * Build a Labeled dataset whose feature vectors
     * are the concatenation of two time-frames.
     *
     * @param array<int,array<string,mixed>> $ohlc
     * @param array<int,array<string,mixed>> $indicators       Primary TF indicators
     * @param array<int,array<string,mixed>> $nextIndicators   Higher TF indicators
     * @param bool $useRegression If true, uses price change percentages as labels
     */
    public function makeMultiTFDataset(
        array $ohlc,
        array $indicators,
        array $nextIndicators,
        FeatureExtractorInterface $fx,
        int $timeframe,
        bool $useRegression = false
    ): Labeled;
}