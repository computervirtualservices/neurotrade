<?php

declare(strict_types=1);

namespace App\Services\CryptoML;

use App\Services\CryptoML\Contracts\MomentumDetectorInterface;

/**
 * Detect upward or downward momentum in a series of technical‑indicator snapshots.
 *
 * Each indicator row must include keys: macd, macd_signal, rsi, close, volume, smma50, ema20, stoch_k, stoch_d, obv.
 */
final class MomentumDetector implements MomentumDetectorInterface
{
    private readonly float $threshold;

    public function __construct(float $threshold = 5.0)
    {
        $this->threshold = $threshold;
    }

    /**
     * @param array<int,array<string,float>> $indicators Chronological indicator snapshots
     */
    public function up(array $indicators): bool
    {
        [$prev2, $prev, $last] = $this->requireLastThree($indicators);
        $score = 0.0;

        $score += $this->macdScore($prev2, $prev, $last, true);
        $score += $this->rsiScore($prev, $last, true);
        $score += $this->priceScore($prev2, $prev, $last, true);
        $score += $this->volumeScore($prev, $last);
        $score += $this->averageScore($last, ['ema9', 'ema21', 'smma21', 'smma50'], true);
        $score += $this->stochasticScore($last, true);
        $score += $this->obvScore($prev, $last, true);
        $score += $this->hiddenDivergenceScore($prev, $last);

        return $score >= $this->threshold;
    }

    /**
     * @param array<int,array<string,float>> $indicators
     */
    public function down(array $indicators): bool
    {
        [$prev2, $prev, $last] = $this->requireLastThree($indicators);
        $score = 0.0;

        $score += $this->macdScore($prev2, $prev, $last, false);
        $score += $this->rsiScore($prev, $last, false);
        $score += $this->priceScore($prev2, $prev, $last, false);
        $score += $this->volumeScore($prev, $last);
        $score += $this->averageScore($last, ['ema9', 'ema21', 'smma21', 'smma50'], false);
        $score += $this->stochasticScore($last, false);
        $score += $this->obvScore($prev, $last, false);

        return $score >= $this->threshold;
    }

    /**
     * Ensure at least 3 snapshots available and return the last three.
     *
     * @throws \InvalidArgumentException
     * @return array<array<string,float>> [$prev2, $prev, $last]
     */
    private function requireLastThree(array $indicators): array
    {
        if (count($indicators) < 3) {
            throw new \InvalidArgumentException('At least 3 indicator snapshots required');
        }

        $lastIndex = array_key_last($indicators);
        return [
            $indicators[$lastIndex - 2],
            $indicators[$lastIndex - 1],
            $indicators[$lastIndex],
        ];
    }

    private function macdScore(array $prev2, array $prev, array $last, bool $bullish): float
    {
        $score = 0.0;
        $macd = 'macd';
        $sig  = 'macd_signal';

        if ($bullish) {
            if ($last[$macd] > $prev[$macd] && $prev[$macd] > $prev2[$macd]) {
                $score += 1.0;
            }
            if ($last[$macd] > $last[$sig] && $prev[$macd] <= $prev[$sig]) {
                $score += 2.0;
            }
        } else {
            if ($last[$macd] < $prev[$macd] && $prev[$macd] < $prev2[$macd]) {
                $score += 1.0;
            }
            if ($last[$macd] < $last[$sig] && $prev[$macd] >= $prev[$sig]) {
                $score += 2.0;
            }
        }

        return $score;
    }

    private function rsiScore(array $prev, array $last, bool $bullish): float
    {
        if ($bullish) {
            return ($last['rsi'] > $prev['rsi'] && $last['rsi'] < 70) ? 1.0 : 0.0;
        }
        return ($last['rsi'] < $prev['rsi'] && $last['rsi'] > 30) ? 1.0 : 0.0;
    }

    private function priceScore(array $prev2, array $prev, array $last, bool $bullish): float
    {
        if ($bullish) {
            return ($last['close'] > $prev['close'] && $prev['close'] > $prev2['close']) ? 1.5 : 0.0;
        }
        return ($last['close'] < $prev['close'] && $prev['close'] < $prev2['close']) ? 1.5 : 0.0;
    }

    private function volumeScore(array $prev, array $last): float
    {
        return ($last['volume'] > $prev['volume'] * 1.1) ? 1.5 : 0.0;
    }

    private function averageScore(array $last, array $keys, bool $above): float
    {
        $score = 0.0;
        foreach ($keys as $key) {
            if ($above ? $last['close'] > $last[$key] : $last['close'] < $last[$key]) {
                $score += 1.0;
            }
        }
        return $score;
    }

    private function stochasticScore(array $last, bool $bullish): float
    {
        $k = $last['stoch_k'];
        $d = $last['stoch_d'];

        if ($bullish) {
            return ($k > $d && $k > 20 && $k < 80) ? 1.0 : 0.0;
        }
        return ($k < $d && $k < 80 && $k > 20) ? 1.0 : 0.0;
    }

    private function obvScore(array $prev, array $last, bool $bullish): float
    {
        if ($bullish) {
            return ($last['obv'] > $prev['obv']) ? 1.0 : 0.0;
        }
        return ($last['obv'] < $prev['obv']) ? 1.0 : 0.0;
    }

    private function hiddenDivergenceScore(array $prev, array $last): float
    {
        if ($last['close'] > $prev['close'] && $last['rsi'] < $prev['rsi'] && $last['rsi'] > 30) {
            return 2.0;
        }
        return 0.0;
    }
}
