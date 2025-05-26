<?php

declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\Contracts\FeatureExtractorInterface;
use App\Services\CryptoML\Contracts\MetricsCalculatorInterface;
use App\Services\CryptoML\Contracts\ModelManagerInterface;
use App\Services\CryptoML\Contracts\MomentumDetectorInterface;
use App\Services\CryptoML\Contracts\RecommendationBuilderInterface;
use App\Services\CryptoML\Contracts\SignalLabelerInterface;
use App\Services\CryptoML\Contracts\SupportResistanceFinderInterface;

final class CryptoPredictor
{
    private int $timeframe = 0;

    public function __construct(
        private readonly FeatureExtractorInterface        $featureExtractor,
        private readonly ModelManagerInterface            $modelManager,
        private readonly SignalLabelerInterface           $signalLabeler,
        private readonly MomentumDetectorInterface        $momentumDetector,
        private readonly RecommendationBuilderInterface   $recommendationBuilder,
        private readonly SupportResistanceFinderInterface $srFinder,
        private readonly MetricsCalculatorInterface       $metrics,
        private readonly IndicatorService                 $indicatorService,
    ) {}

    /**
     * Predict the next trading signal, its confidence, and a human-readable recommendation.
     */
    public function predict(
        array $ohlc,
        array $indicators,
        float $currentPrice,
        int   $timeframe,
    ): array {
        $this->timeframe = $timeframe;
        if (empty($ohlc) || empty($indicators)) {
            return $this->defaultResponse($timeframe);
        }

        $lastBar  = end($ohlc);
        $features = $this->featureExtractor
            ->extract($indicators, $lastBar, count($indicators) - 1);

        [$prediction, $confidence] = $this->modelManager
            ->predictWithConfidence($features);

        // Handle differently based on if we're using a regression or classification model
        if ($this->modelManager->isRegressor()) {
            // For regression models, prediction is the expected price change percentage
            return $this->handleRegressionPrediction($prediction, $confidence, $indicators, $currentPrice, $timeframe);
        } else {
            // For classification models, use existing logic
            if ($prediction === SignalLabeler::NEUTRAL) {
                $prediction = $this->applyMomentumOverride($indicators, $prediction);
            }

            $recommendation = $this->recommendationBuilder->build(
                $prediction,
                $confidence,
                $indicators,
                $currentPrice,
                $this->srFinder,
                $timeframe
            );

            return [
                'signal'         => $prediction,
                'confidence'     => $confidence,
                'recommendation' => $recommendation,
            ];
        }
    }

    public function createModel(string $pair, int $interval): void
    {
        $this->modelManager->createModel($pair, $interval);
    }

    public function clearModel(): void
    {
        $this->modelManager->clearModel();
    }

    public function clearSessionData()
    {
        $this->modelManager->clearSessionData();
    }

    public function resetState()
    {
        $this->modelManager->resetState();
    }

    /**
     * Train (and optionally cross-validate) the underlying ML model.
     */
    public function train(
        array $ohlc,
        array $indicators,
        bool  $crossValidate = true,
        int   $timeframe,
        bool  $useRegression = false
    ): array {
        $dataset = $this->signalLabeler
            ->makeDataset($ohlc, $indicators, $this->featureExtractor, $timeframe, $useRegression);

        return $this->modelManager->train($dataset, $crossValidate, $timeframe, $useRegression);
    }

    /**
     * Train the model on two timeframes (primary + higher).
     */
    public function trainMultiTF(
        array $ohlc,
        array $indicators,
        array $nextIndicators,
        bool  $crossValidate = true,
        int   $timeframe,
        bool  $useRegression = false
    ): array {
        $dataset = $this->signalLabeler->makeMultiTFDataset(
            $ohlc,
            $indicators,
            $nextIndicators,
            $this->featureExtractor,
            $timeframe,
            $useRegression
        );
        return $this->modelManager->train($dataset, $crossValidate, $timeframe, $useRegression);
    }

    /**
     * Predict using two timeframes.
     */
    public function predictMultiTF(
        array $ohlc,
        array $indicators,
        array $nextIndicators,
        float $currentPrice,
        int   $timeframe,
    ): array {
        if ([] === $ohlc || [] === $indicators) {
            return $this->defaultResponse($timeframe);
        }

        $lastBar = end($ohlc);
        $f1      = $this->featureExtractor->extract($indicators,     $lastBar, count($indicators) - 1);
        $f2      = $this->featureExtractor->extract($nextIndicators, $lastBar, count($nextIndicators) - 1);
        $features = array_merge($f1, $f2);

        [$prediction, $confidence] = $this->modelManager
            ->predictWithConfidence($features);

        // Handle differently based on if we're using a regression or classification model
        if ($this->modelManager->isRegressor()) {
            // For regression models, prediction is the expected price change percentage
            return $this->handleRegressionPrediction($prediction, $confidence, $indicators, $currentPrice, $timeframe);
        } else {
            // For classification models, use existing logic
            if ($prediction === SignalLabeler::NEUTRAL) {
                $prediction = $this->applyMomentumOverride($indicators, $prediction);
            }

            $recommendation = $this->recommendationBuilder->build(
                $prediction,
                $confidence,
                $indicators,
                $currentPrice,
                $this->srFinder,
                $timeframe
            );

            return compact('prediction', 'confidence', 'recommendation');
        }
    }

    /**
     * Expose model's feature-importance scores.
     */
    public function featureImportance(): array
    {
        return $this->modelManager->featureImportance();
    }

    /**
     * Handle predictions from a regression model specifically
     */
    private function handleRegressionPrediction(
        float $prediction,
        float $confidence,
        array $indicators,
        float $currentPrice,
        int   $timeframe
    ): array {
        // 1) Precompute your thresholds & context
        // Switch the timeframe context
        $this->indicatorService->setTimeframe($timeframe);

        // Now pull thresholds (if you really need raw values):
        // $ctx = $this->indicatorService->getContext();
        // $strongThr      = $ctx['strong_move'];   // ≥2.5% → “strong” move
        // $upThr          = $ctx['uptrend'];   // ≥1.5% → established uptrend
        // $startThr       = $ctx['trend_start'];  // ≥0.75% → uptrend start
        // $momentumThr    = $ctx['momentum_threshold'];   // ≥0.3% → momentum

        // // Profit‐taking thresholds (hard‐coded here or could be added to $ctx)
        // $profit1Thr   = 1.0;
        // $profit3Thr   = 3.0;

        // $noiseBand    = 0.3;
        // $choppyMin    = $noiseBand;
        // $choppyMax    = $startThr;

        // $barIndex     = count($indicators) - 1;
        // $breakOut     = $this->metrics->detectBreakout($indicators, $barIndex);
        // $breakDown    = $this->metrics->detectBreakdown($indicators, $barIndex);

        // $last         = end($indicators) ?: [];
        // $rsi          = $last['rsi'] ?? 0;

        // 2) Now the match
        // $signal = match (true) {
        //     // ACTION_MAP keys in preferred order:
        //     $breakOut                                => SignalLabeler::BREAKOUT,
        //     $breakDown                               => SignalLabeler::BREAKDOWN,
        //     $prediction >= $strongThr                => SignalLabeler::STRONG_UPTREND,
        //     $prediction >= $upThr                    => SignalLabeler::UPTREND,
        //     $prediction >= $startThr                 => SignalLabeler::UPTREND_START,
        //     // UPTREND_END: small pullback but very high RSI
        //     $prediction < 0 && ($last['rsi'] ?? 0) > 70 => SignalLabeler::UPTREND_END,
        //     $prediction <= -$strongThr               => SignalLabeler::STRONG_REVERSAL,
        //     $prediction <= -$upThr                   => SignalLabeler::REVERSAL,
        //     abs($prediction) < $noiseBand            => SignalLabeler::CONSOLIDATION,
        //     (abs($prediction) >= $choppyMin
        //         && abs($prediction) < $choppyMax)   => SignalLabeler::CHOPPY,
        //     $prediction >= $momentumThr              => SignalLabeler::MOMENTUM_UP,
        //     $prediction <= -$momentumThr             => SignalLabeler::MOMENTUM_DOWN,
        //     default                                  => SignalLabeler::NEUTRAL,
        // };

        [
            'momentum'   => $momentumThr,
            'trend_start' => $startThr,
            'uptrend'    => $upThr,
            'strong_move' => $strongThr,
            'profit1'    => $profit1Thr,
            'profit3'    => $profit3Thr,
        ] = $this->indicatorService->getContext();
        
        // Noise band equals momentum threshold by default
        $noiseBand = $momentumThr;

        // 2) Detect structural signals
        $barIndex  = count($indicators) - 1;
        $breakOut  = $this->metrics->detectBreakout($indicators,  $barIndex);
        $breakDown = $this->metrics->detectBreakdown($indicators, $barIndex);

        $last = end($indicators) ?: [];
        $rsi  = $last['rsi'] ?? 0.0;

        // 3) Match to high-level signal
        $signal = match (true) {
            // Structural breakouts always take precedence
            $breakOut                                 => SignalLabeler::BREAKOUT,
            $breakDown                                => SignalLabeler::BREAKDOWN,

            // STRONG_UPTREND: large predicted move + strong trend + bullish momentum
            $prediction >= $strongThr
                && ($last['adx'] ?? 0) >= 30
                && ($last['macd']    ?? 0) >= ($last['macd_signal'] ?? 0)
            => SignalLabeler::STRONG_UPTREND,

            // UPTREND: medium predicted move + confirming trend strength
            $prediction >= $upThr
                && ($last['adx'] ?? 0) >= 25
            => SignalLabeler::UPTREND,

            // UPTREND_START: small predicted move, early trend signal
            $prediction >= $startThr
                && ($last['adx'] ?? 0) >= 20
            => SignalLabeler::UPTREND_START,

            // PROFIT targets (purely ML-driven, no extra filters)
            $prediction >= $profit3Thr                 => SignalLabeler::PROFIT_UP_3,
            $prediction >= $profit1Thr                 => SignalLabeler::PROFIT_UP_1,

            $prediction <= -$profit3Thr                => SignalLabeler::PROFIT_DOWN_3,
            $prediction <= -$profit1Thr                => SignalLabeler::PROFIT_DOWN_1,

            // UPTREND_END: small drop in uptrend when RSI is overbought
            $prediction < 0.0
                && $rsi > 70.0
            => SignalLabeler::UPTREND_END,

            // REVERSALS: predicted down moves with trend confirmation
            $prediction <= -$strongThr
                && ($last['adx'] ?? 0) >= 30
            => SignalLabeler::STRONG_REVERSAL,

            $prediction <= -$upThr
                && ($last['adx'] ?? 0) >= 25
            => SignalLabeler::REVERSAL,

            // Consolidation vs. choppy range
            abs($prediction) < $noiseBand              => SignalLabeler::CONSOLIDATION,
            abs($prediction) < $startThr               => SignalLabeler::CHOPPY,

            // Pure momentum breakouts
            $prediction >= $momentumThr                => SignalLabeler::MOMENTUM_UP,
            $prediction <= -$momentumThr               => SignalLabeler::MOMENTUM_DOWN,

            // Fallback
            default                                   => SignalLabeler::NEUTRAL,
        };

        // fallback to momentum if truly neutral
        if ($signal === SignalLabeler::NEUTRAL) {
            $signal = $this->applyMomentumOverride($indicators, $signal);
        }

        $recommendation = $this->recommendationBuilder->build(
            $signal,
            $confidence,
            $indicators,
            $currentPrice,
            $this->srFinder,
            $timeframe
        );

        return compact('signal', 'confidence', 'prediction', 'recommendation');
    }


    private function defaultResponse(int $timeframe): array
    {
        return [
            'signal'         => SignalLabeler::NEUTRAL,
            'confidence'     => 0.0,
            'recommendation' => $this->recommendationBuilder->build(
                SignalLabeler::NEUTRAL,
                0.0,
                [],
                0.0,
                $this->srFinder,
                $timeframe
            ),
        ];
    }

    private function applyMomentumOverride(
        array  $indicators,
        string $currentSignal,
    ): string {
        if ($this->momentumDetector->up($indicators)) {
            return SignalLabeler::MOMENTUM_UP;
        }

        if ($this->momentumDetector->down($indicators)) {
            return SignalLabeler::MOMENTUM_DOWN;
        }

        return $currentSignal;
    }
}
