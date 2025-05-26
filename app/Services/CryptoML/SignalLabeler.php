<?php

declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\Contracts\MetricsCalculatorInterface;
use App\Services\CryptoML\Contracts\FeatureExtractorInterface;
use App\Services\CryptoML\Contracts\SignalLabelerInterface;
use Illuminate\Support\Facades\Log;
use Rubix\ML\Datasets\Labeled;

/**
 * Generates supervised-learning labels from price/indicator history.
 */
final class SignalLabeler implements SignalLabelerInterface
{
    // Public signal constants
    public const STRONG_UPTREND   = 'STRONG_UPTREND';
    public const UPTREND_START    = 'UPTREND_START';
    public const UPTREND          = 'UPTREND';
    public const UPTREND_END      = 'UPTREND_END';
    public const STRONG_REVERSAL  = 'STRONG_REVERSAL';
    public const CONSOLIDATION    = 'CONSOLIDATION';
    public const CHOPPY           = 'CHOPPY';
    public const REVERSAL         = 'REVERSAL';
    public const NEUTRAL          = 'NEUTRAL';
    public const MOMENTUM_UP      = 'MOMENTUM_UP';
    public const MOMENTUM_DOWN    = 'MOMENTUM_DOWN';
    public const BREAKOUT         = 'BREAKOUT';
    public const BREAKDOWN        = 'BREAKDOWN';
    public const PROFIT_UP_1      = 'PROFIT_UP_1';
    public const PROFIT_UP_3      = 'PROFIT_UP_3';
    public const PROFIT_DOWN_1    = 'PROFIT_DOWN_1';
    public const PROFIT_DOWN_3    = 'PROFIT_DOWN_3';

    public const MIN_CANDLES   = 40;
    public const WARMUP        = 35;
    public const LOOKAHEAD     = 5;

    // Add volatility-based thresholds
    private const MIN_CHANGE_THRESHOLD = 0.1; // Minimum 0.1% change to be meaningful
    private const MAX_CHANGE_THRESHOLD = 10.0; // Cap extreme movements at 10%

    public function __construct(
        private readonly MetricsCalculatorInterface $metrics,
    ) {}

    /**
     * @inheritDoc
     */
    public function makeDataset(
        array $ohlc,
        array $indicators,
        FeatureExtractorInterface $fx,
        int $timeframe,
        bool $useRegression = false
    ): Labeled {
        $total = count($ohlc);
        if ($total < self::MIN_CANDLES) {
            throw new \RuntimeException(
                sprintf('Need at least %d candles, got %d', self::MIN_CANDLES, $total)
            );
        }

        $avgVol = $this->metrics->averageVolatility($indicators, self::MIN_CANDLES);
        $start  = self::WARMUP;
        $end    = $total - (self::LOOKAHEAD + 1);

        $samples = [];
        $labels  = [];

        for ($i = $start; $i <= $end; $i++) {
            $samples[] = $fx->extract($indicators, $ohlc[$i], $i);
            // For regression, we predict actual percentage change
            if ($useRegression) {
                // For regression, we predict actual percentage change
                $labels[] = $this->derivePriceChangeLabel($ohlc, $i);
            } else {
                // For classification, use categorical labels
                // $labels[] = $this->deriveLabel($ohlc, $indicators, $i, $avgVol);
                $deriver = new LabelDeriver($timeframe); // e.g. 30, 240, 1440, etc.
                $labels[]   = $deriver->deriveLabel($ohlc, $indicators, $i, $avgVol);
            }
        }

        return new Labeled($samples, $labels);
    }

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
    ): Labeled {
        $total = count($ohlc);
        if ($total < self::MIN_CANDLES || count($nextIndicators) < self::MIN_CANDLES) {
            throw new \RuntimeException(
                sprintf('Need at least %d candles, got %d', self::MIN_CANDLES, $total < self::MIN_CANDLES ? $total : count($nextIndicators))
            );
        }

        $samples = [];
        $labels  = [];
        $avgVol  = $this->metrics->averageVolatility($indicators, 30);

        $start  = self::WARMUP;
        $end    = $total - (self::LOOKAHEAD + 1);

        for ($i = $start; $i < $end; ++$i) {
            // primary+higher features
            $f1 = $fx->extract($indicators,     $ohlc[$i], $i);
            $f2 = $fx->extract($nextIndicators, $ohlc[$i], $i);
            $samples[] = array_merge($f1, $f2);

            if ($useRegression) {
                // For regression, use actual percentage price change
                $labels[] = $this->derivePriceChangeLabel($ohlc, $i);
            } else {
                // For classification, use categorical labels
                $deriver = new LabelDeriver($timeframe);
                $label[] = $deriver->deriveLabel($ohlc, $indicators, $i, $avgVol);
            }
        }

        $labels = new Labeled($samples, $labels);

        return $labels;
    }

    /**
     * Calculate the percentage price change N bars ahead
     * Used for regression models
     */
    private function derivePriceChangeLabel(array $ohlc, int $i): float
    {
        $currentClose = $ohlc[$i]['y'][3];
        $futureClose = $ohlc[$i + self::LOOKAHEAD]['y'][3];

        // Calculate percentage change
        if ($currentClose <= 0) {
            return 0.0;
        }

        // return (($futureClose - $currentClose) / $currentClose) * 100.0;

        // Use log returns for better statistical properties
        $logReturn = log($futureClose / $currentClose);

        // Cap extreme values to prevent outliers from dominating
        return max(min($logReturn, self::MAX_CHANGE_THRESHOLD), -self::MAX_CHANGE_THRESHOLD);
    }

    /**
     * Check if features contain invalid values (NaN, Inf, null)
     */
    private function hasInvalidFeatures(array $features): bool
    {
        foreach ($features as $feature) {
            if (!is_numeric($feature) || !is_finite($feature) || is_null($feature)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate regression target values
     */
    private function isValidRegressionTarget(float $target): bool
    {
        // Filter out very small changes (likely noise) and extreme outliers
        return is_finite($target) &&
            abs($target) >= self::MIN_CHANGE_THRESHOLD &&
            abs($target) <= self::MAX_CHANGE_THRESHOLD;
    }
}
