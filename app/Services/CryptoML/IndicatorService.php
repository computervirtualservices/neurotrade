<?php

namespace App\Services\CryptoML;

/**
 * Centralized indicator thresholds by timeframe (minutes) and mode,
 * OHLC interval options, and trade decision logic.
 */
class IndicatorService
{
    /**
     * OHLC interval options (label => minutes)
     */
    private const OPTIONS = [
        'Choose' => 0,
        '1M'     => 1,
        '5M'     => 5,
        '15M'    => 15,
        '30M'    => 30,
        '1H'     => 60,
        '4H'     => 240,
        '1D'     => 1440,
        '1W'     => 10080,
        '3W'     => 21600,
    ];

    /**
     * Thresholds map: label => [regressor => [...], classifier => [...]]
     * Each regressor array: [momentum, trend_start, uptrend, strong_move, profit1, profit3]
     */
    private const THRESHOLDS = [
        '1M'  => [
            'regressor'  => [0.002, 0.005, 0.010, 0.015, 0.01, 0.03],
            'classifier' => [0.2,    0.5,    1.0,    2.0,   0.01, 0.03],
        ],
        '5M'  => [
            'regressor'  => [0.004, 0.010, 0.020, 0.030, 0.01, 0.03],
            'classifier' => [0.3,    0.75,   1.5,    2.5,   0.01, 0.03],
        ],
        '15M' => [
            'regressor'  => [0.006, 0.015, 0.030, 0.045, 0.01, 0.03],
            'classifier' => [0.4,    1.0,    2.0,    3.5,   0.01, 0.03],
        ],
        '30M' => [
            'regressor'  => [0.008, 0.020, 0.040, 0.060, 0.01, 0.03],
            'classifier' => [0.6,    1.5,    3.0,    5.0,   0.01, 0.03],
        ],
        '1H'  => [
            'regressor'  => [0.010, 0.025, 0.050, 0.075,  0.01, 0.03],
            'classifier' => [0.75,   1.875,  3.75,   6.25,   0.01, 0.03],
        ],
        '4H'  => [
            'regressor'  => [0.015, 0.040, 0.080, 0.120,  0.01, 0.03],
            'classifier' => [0.9,    2.25,   4.5,    7.5,   0.01, 0.03],
        ],
        '1D'  => [
            'regressor'  => [0.020, 0.050, 0.100, 0.150, 0.01, 0.03],
            'classifier' => [1.5,    3.75,   7.5,    12.5,   0.01, 0.03],
        ],
        '1W'  => [
            'regressor'  => [0.040, 0.100, 0.200, 0.300,   0.01, 0.03],
            'classifier' => [3.0,    7.5,    15.0,   25.0,   0.01, 0.03],
        ],
        '3W'  => [
            'regressor'  => [0.080, 0.200, 0.400, 0.600,   0.01, 0.03],
            'classifier' => [6.0,    15.0,   30.0,   50.0,   0.01, 0.03],
        ],
    ];


    private float $momentumThreshold;
    private float $trendStartThreshold;
    private float $uptrendThreshold;
    private float $strongMoveThreshold;
    private float $profit1Threshold;
    private float $profit3Threshold;
    private int   $timeframeMinutes;
    private bool  $isRegressor;

    public function __construct(?int $timeframeMinutes = null, ?bool $isRegressor = null)
    {
        $minutes = $timeframeMinutes ?? config('cryptoml.default_timeframe', 1440);
        $label   = $this->getLabelForMinutes($minutes);

        $this->timeframeMinutes = $minutes;
        $this->isRegressor      = $isRegressor ?? config('cryptoml.is_regressor', false);
        $this->applyThresholds($label);
    }

    private function getLabelForMinutes(int $minutes): string
    {
        $label = array_search($minutes, self::OPTIONS, true);
        if ($label === false) {
            throw new \InvalidArgumentException("Unsupported timeframe: {$minutes} minutes.");
        }
        return $label;
    }

    private function applyThresholds(string $label): void
    {
        $mode = $this->isRegressor ? 'regressor' : 'classifier';
        $vals = self::THRESHOLDS[$label][$mode];

        // Unpack regressor array
        [
            $this->momentumThreshold,
            $this->trendStartThreshold,
            $this->uptrendThreshold,
            $this->strongMoveThreshold,
            $this->profit1Threshold,
            $this->profit3Threshold
        ] = $vals;
    }

    public function setTimeframe(int $minutes): void
    {
        $label = $this->getLabelForMinutes($minutes);
        $this->timeframeMinutes = $minutes;
        $this->applyThresholds($label);
    }

    public function setMode(bool $isRegressor): void
    {
        $this->isRegressor = $isRegressor;
        $this->applyThresholds($this->getLabelForMinutes($this->timeframeMinutes));
    }

    public function getContext(): array
    {
        return [
            'timeframe_minutes'  => $this->timeframeMinutes,
            'mode'               => $this->isRegressor ? 'regressor' : 'classifier',
            'momentum'           => $this->momentumThreshold,
            'trend_start'        => $this->trendStartThreshold,
            'uptrend'            => $this->uptrendThreshold,
            'strong_move'        => $this->strongMoveThreshold,
            'profit1'            => $this->profit1Threshold,
            'profit3'            => $this->profit3Threshold,
        ];
    }

    // Predicate methods...
    public function isMomentum(float $c): bool
    {
        return $c >= $this->momentumThreshold;
    }
    public function isTrendStart(float $c): bool
    {
        return $c >= $this->trendStartThreshold;
    }
    public function isUptrend(float $c): bool
    {
        return $c >= $this->uptrendThreshold;
    }
    public function isStrongMove(float $c): bool
    {
        return $c >= $this->strongMoveThreshold;
    }
    public function isProfit1(float $c): bool
    {
        return $c >= $this->profit1Threshold;
    }
    public function isProfit3(float $c): bool
    {
        return $c >= $this->profit3Threshold;
    }

    public function getOptions(): array
    {
        return self::OPTIONS;
    }
}
