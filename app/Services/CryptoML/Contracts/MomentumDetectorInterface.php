<?php
declare(strict_types=1);

namespace App\Services\CryptoML\Contracts;

use Rubix\ML\Datasets\Labeled;

/**
 * Detects bullish or bearish momentum based on recent indicators.
 */
interface MomentumDetectorInterface
{
    /**
     * @param array<int,array<string,float>> $indicators
     */
    public function up(array $indicators): bool;

    /**
     * @param array<int,array<string,float>> $indicators
     */
    public function down(array $indicators): bool;
}