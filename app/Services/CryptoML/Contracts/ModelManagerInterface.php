<?php
declare(strict_types=1);

namespace App\Services\CryptoML\Contracts;

use Rubix\ML\Datasets\Labeled;

interface ModelManagerInterface
{

    /**
     * Create a new model instance.
     */
    public function createModel(string $pair, int $interval);

    /**
     * Clear the current model and return its function
     */
    public function clearModel();

    /**
     * Reset the current model to its initial state.
     */
    public function resetState();

    /**
     * Clear any session data that may be stored in memory.
     */
    public function clearSessionData();


    /**
     * Train the model using labeled dataset and return performance metrics.
     */
    public function train(Labeled $dataset, bool $crossValidate = true, int $timeframe = 30, bool $useRegression = false): array;

    /**
     * Predict using input features and return [prediction, confidence].
     */
    public function predictWithConfidence(array $features): array;

    /**
     * Return feature importance scores keyed by feature name.
     * @return array<string,float>
     */
    public function featureImportance(): array;
    
    /**
     * Returns whether the model is a regressor or classifier
     */
    public function isRegressor(): bool;
}