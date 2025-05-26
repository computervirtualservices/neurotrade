<?php
declare(strict_types=1);

namespace App\Services\CryptoML\Contracts;

use Rubix\ML\Datasets\Labeled;

/**
 * Builds a human-readable trading recommendation from a model signal.
 */
interface RecommendationBuilderInterface
{
    /**
     * Return all signals that map to a given action (BUY, SELL, etc).
     */
    public function signalsForAction(string $action): array;

    /**
     * @param string                         $signal      Model-predicted signal constant
     * @param float                          $confidence  Probability score [0..1]
     * @param array<int,array<string,mixed>> $indicators  Recent indicator snapshots
     * @param float                          $price       Current market price
     * @return array<string,mixed> Recommendation details (action, stops, targets, etc.)
     */
    public function build(string $signal, float $confidence, array $indicators, float $price, SupportResistanceFinderInterface $srFinder, int $interval): array;
}