<?php

declare(strict_types=1);

namespace App\Helpers;

final class OHLCIndicators
{
    /**
     * Label → minutes.  “Choose” is our default/no-selection.
     */
    private const OPTIONS = [
        'Choose' => 0,     // no interval selected
        '1M'      => 1,
        //'3M'      => 3,
        '5M'      => 5,
        '15M'     => 15,
        '30M'     => 30,
        '1H'      => 60,
        //'2H'      => 120,
        '4H'      => 240,
        //'6H'      => 360,
        //'12H'     => 720,
        '1D'      => 1440,
        //'3D'      => 4320,
        '1W'      => 10080,
        '3W'      => 21600,
        //'1MTH'    => 43200,
    ];

    /**
     * All minute-values in ascending order, including zero for “Choose.”
     */
    private const SORTED = [
        0,
        1,
        5,
        15,
        30,
        60,
        240,
        1440,
        10080,
        21600,
    ];

    /** Get all label→minutes mappings. */
    public static function all(): array
    {
        return self::OPTIONS;
    }

    /** Get minutes value for a given label (e.g. '15M'→15, 'Choose'→0). */
    public static function minutes(string $label): int
    {
        return self::OPTIONS[$label] ?? 0;
    }

    /** Get label for a given minutes value, or null if none. */
    public static function label(int $minutes): ?string
    {
        return array_search($minutes, self::OPTIONS, true) ?: null;
    }

    /**
     * Find the next‐higher interval in minutes.
     * If you pass 0 (“Choose”), returns the first real interval (1).
     * If you’re already at the maximum, returns null.
     */
    public static function nextInterval(int $minutes): ?int
    {
        // If at 21600 (3 Weeks), there is no higher interval
        if ($minutes === 21600) {
            return 21600;
        }

        $skipped = false;

        foreach (self::SORTED as $m) {
            if ($m <= $minutes) {
                continue;
            }

            // // skip the very next one
            // if (! $skipped) {
            //     $skipped = true;
            //     continue;
            // }

            // return the second-next interval
            return $m;
        }

        return null;
    }


    /**
     * Find the next‐lower interval in minutes.
     * If you pass 0 (“Choose”), returns null (there is no lower).
     */
    public static function prevInterval(int $minutes): ?int
    {
        $prev = null;
        foreach (self::SORTED as $m) {
            if ($m >= $minutes) {
                break;
            }
            $prev = $m;
        }
        return $prev;
    }
}
