<?php

declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\Contracts\ModelManagerInterface;
use Exception;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface as Logger;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem as Persister;
use Rubix\ML\Pipeline;
use Rubix\ML\Transformers\{
    NumericStringConverter,
    MissingDataImputer,
    MinMaxNormalizer
};
use Rubix\ML\Classifiers\{
    ClassificationTree,
    RandomForest
};
use Rubix\ML\Regressors\{
    RegressionTree,
    Ridge,
    GradientBoost
};
use Rubix\ML\CrossValidation\Reports\MulticlassBreakdown;
use Rubix\ML\CrossValidation\Reports\ResidualAnalysis;
use Rubix\ML\CrossValidation\Metrics\{RMSE, RSquared};

final class ModelManager implements ModelManagerInterface
{
    private  PersistentModel $model;
    private string          $modelPath;
    private  float           $confidenceThreshold;
    private  int             $cacheTtl;
    private bool            $isRegressor;
    private Filesystem      $storage;
    private Logger          $logger;

    public function __construct(
        string          $modelPath,
        float           $confidenceThreshold,
        int             $cacheTtl,
        Filesystem      $storage,
        CacheRepository $cache,
        Logger          $logger,
        bool            $isRegressor = false,
    ) {
        $this->modelPath           = $modelPath;
        $this->confidenceThreshold = $confidenceThreshold;
        $this->cacheTtl            = $cacheTtl;
        $this->isRegressor         = $isRegressor;
        $this->storage             = $storage;
        $this->logger              = $logger;
    }

    public function createModel(string $pair, int $interval): void
    {
        $this->model = $this->initializeModel($this->storage, $this->logger, $pair, $interval);
    }


    public function train(Labeled $dataset, bool $crossValidate = true, int $timeframe = 30, bool $useRegression = false): array
    {
        try {
            $this->isRegressor = $useRegression;

            $start = microtime(true);

            $this->model->train($dataset);

            $cvReport = $crossValidate
                ? $this->crossValidate($dataset)
                : null;

            $this->model->save();

            $duration = microtime(true) - $start;
            
            return [
                'samples'           => $dataset->numSamples(),
                'training_time_sec' => $duration,
                'cross_validation'  => $cvReport,
                'feature_importance' => $this->featureImportance(),
            ];
        } catch (Exception $e) {
            // Log the error message
            Log::error("Error training model: " . $e->getMessage());
            return [
                'error'   => true,
                'message' => $e->getMessage(),
                'samples' => 0,
            ];
        }
    }

    /**
     * Resets the model state while preserving the model architecture.
     * This is less resource-intensive than completely reinitializing the model.
     * 
     * @return void
     */
    public function resetState(): void
    {
        // Keep the model architecture but reset its internal state
        // without destroying and recreating the entire pipeline
        if (isset($this->model)) {
            $estimator = $this->unwrapEstimator();

            // Reset RandomForest or GradientBoost internal state if possible
            if (method_exists($estimator, 'cleanup')) {
                $estimator->cleanup();
            }

            // Reset cached predictions or other temporary data
            if (property_exists($estimator, 'predictions')) {
                $estimator->predictions = [];
            }

            // Clear internal cache
            cache()->forget('crypto_feature_importance');

            $this->logger->info("Reset model state while preserving architecture");
        }
    }

    /**
     * Cleans up memory and resources between processing iterations.
     * Helps prevent memory leaks when processing multiple pairs.
     * 
     * @return void
     */
    public function clearSessionData(): void
    {
        // Free any large datasets that might be held in memory
        $this->freeLargeDatasets();

        // Clear PHP's internal object cache to release memory
        if (!gc_enabled()) {
            gc_enable();
        }

        // Force garbage collection
        gc_collect_cycles();

        // Log memory usage for debugging purposes
        $memUsage = $this->formatBytes(memory_get_usage(true));
        $this->logger->debug("Memory usage after clearing session data: {$memUsage}");
    }

    /**
     * Free any large datasets or temporary variables that might be held in memory
     * 
     * @return void
     */
    private function freeLargeDatasets(): void
    {
        // Clear any dataset references that might be stored within the class
        if (property_exists($this, 'trainingData')) {
            unset($this->trainingData);
        }

        // Clear any cached predictions
        if (property_exists($this, 'predictions')) {
            unset($this->predictions);
        }

        // If Rubix ML is holding onto large datasets inside the estimator
        $est = $this->unwrapEstimator();

        // For RandomForest, try to clean up individual trees
        if ($est instanceof \Rubix\ML\Classifiers\RandomForest) {
            if (method_exists($est, 'trees') && is_array($est->trees())) {
                foreach ($est->trees() as $tree) {
                    if (method_exists($tree, 'cleanup')) {
                        $tree->cleanup();
                    }
                }
            }
        }

        // For GradientBoost, try to clean up weak learners
        if ($est instanceof \Rubix\ML\Regressors\GradientBoost) {
            if (property_exists($est, 'weak') && is_array($est->weak)) {
                foreach ($est->weak as $learner) {
                    if (method_exists($learner, 'cleanup')) {
                        $learner->cleanup();
                    }
                }
            }
        }
    }

    /**
     * Formats bytes to human-readable format for logging
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Delete any on‐disk model file and reset the in‐memory model
     */
    public function clearModel(): void
    {
        $absPath = $this->storage->path($this->modelPath);

        // 1) Remove the file if it exists
        if ($this->storage->exists($this->modelPath)) {
            $this->storage->delete($this->modelPath);
            $this->logger->info("Deleted old model file at {$this->modelPath}");
        }

        // 2) Reinitialize a clean PersistentModel
        $this->model = new PersistentModel(
            $this->buildPipeline(),
            new Persister($absPath)
        );

        $this->logger->info("Reinitialized fresh model pipeline");
    }

    public function predictWithConfidence(array $features): array
    {
        $unlabeled = new Unlabeled([$features]);

        if ($this->isRegressor) {
            // Regression model returns a numeric prediction
            $prediction = $this->model->predict($unlabeled)[0];
            // For regression, we don't have probabilities, so use a placeholder confidence
            $confidence = 1.0;

            return [$prediction, $confidence];
        } else {
            // Classification model
            $prediction = $this->model->predict($unlabeled)[0];

            // Get class probabilities if supported
            $proba = method_exists($this->model, 'proba')
                ? $this->model->proba($unlabeled)[0]
                : [];

            // Confidence is probability of the chosen class
            $confidence = 0.0;
            if (!empty($proba) && array_key_exists($prediction, $proba)) {
                $confidence = $proba[$prediction];
            } elseif (!empty($proba)) {
                // Fallback to max probability if label key mismatch
                $confidence = max($proba);
            }

            return [$prediction, $confidence];
        }
    }

    public function featureImportance(): array
    {
        return cache()->remember(
            'crypto_feature_importance',
            $this->cacheTtl * 60,
            fn() => $this->computeFeatureImportance()
        );
    }

    public function isRegressor(): bool
    {
        return $this->isRegressor;
    }

    /* ──────────────────────────────────────────────────── */
    /*                     PRIVATE HELPERS                 */
    /* ──────────────────────────────────────────────────── */

    private function initializeModel(Filesystem $storage, Logger $logger, string $pair, int $interval): PersistentModel
    {
        $pairName = str_replace('/', '_', strtolower($pair));
        if (config('cryptoml.is_regressor')) {
            $regressor = 'regressor';
        } else {
            $regressor = 'classifier';
        }
        $this->modelPath = "ml_models/crypto_predictor_{$pairName}_{$interval}_{$regressor}.rbx";

        $absPath = $storage->path($this->modelPath);
        $this->ensureDirectoryExists(dirname($absPath));

        // Only auto‐load if we're _not_ in regression mode
        if ($storage->exists($this->modelPath)) {
            try {
                $model = PersistentModel::load(new Persister($absPath));
                $logger->info("Loaded ML model from {$this->modelPath}");
                return $model;
            } catch (\Throwable $e) {
                $logger->warning("Failed loading model: " . $e->getMessage());
            }
        }

        $logger->info("Initializing new ML model pipeline");
        return new PersistentModel(
            $this->buildPipeline(),
            new Persister($absPath)
        );
    }

    private function buildPipeline(): Pipeline
    {
        if ($this->isRegressor) {
            // Building a regression model pipeline
            // Ridge is a good general-purpose linear regression with regularization
            $regressor = new Ridge(0.1);

            // For more advanced regression, you could use GradientBoost with RegressionTree
            // $tree = new RegressionTree(30);
            // $regressor = new GradientBoost($tree, 100, 0.1);

            return new Pipeline([
                new NumericStringConverter(),
                new MissingDataImputer(),
                new MinMaxNormalizer(),
            ], $regressor);
        } else {
            // Original classification pipeline
            $tree = new ClassificationTree(100);
            $forest = new RandomForest($tree, 100, 0.1, true);

            return new Pipeline([
                new NumericStringConverter(),
                new MissingDataImputer(),
                new MinMaxNormalizer(),
            ], $forest);
        }
    }

    private function crossValidate(Labeled $dataset): array
    {
        // Clone the whole pipeline + learner
        $clone = clone $this->model;

        // Generate raw predictions
        $predsRaw = $clone->predict(new Unlabeled($dataset->samples()));

        if ($this->isRegressor) {
            // Convert both preds and labels to floats
            $preds  = array_map('floatval', $predsRaw);
            $labels = array_map('floatval', $dataset->labels());

            // Compute numeric regression metrics
            $rmse = (new RMSE())->score($preds, $labels);
            $r2   = (new RSquared())->score($preds, $labels);

            return [
                'rmse'      => $rmse,
                'r_squared' => $r2,
            ];
        }

        // Classification branch: labels are strings, so use breakdown report
        $report = (new MulticlassBreakdown())
            ->generate($predsRaw, $dataset->labels());

        return $report->toArray();
    }

    private function computeFeatureImportance(): array
    {
        $estimator = $this->unwrapEstimator();
        if (! method_exists($estimator, 'featureImportances')) {
            return [];
        }

        $scores = $estimator->featureImportances();
        $map = FeatureExtractor::getFeatureMap();

        $result = [];
        foreach ($scores as $i => $score) {
            $key = $map[$i] ?? "f{$i}";
            $result[$key] = $score;
        }

        return $result;
    }

    private function unwrapEstimator(): object
    {
        $est = $this->model;

        // 1) Unwrap your own wrapper
        if ($est instanceof PersistentModel) {
            $est = $est->base();
        }

        // 2) Unwrap any Pipeline if you have one
        if ($est instanceof Pipeline) {
            $est = $est->base();
        }

        return $est;
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Unable to create directory {$dir}");
        }
    }
}
