<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Container\Attributes\Log;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

final class KrakenMarketData
{
    /**
     * Kraken-supported OHLC intervals (in minutes).
     */
    public const VALID_OHLC_INTERVALS = [
        1,    // 1 minute
        //3,    // 3 minutes
        5,    // 5 minutes
        15,   // 15 minutes
        30,   // 30 minutes
        60,   // 1 hour
        //120,  // 2 hours
        240,  // 4 hours
        //360,  // 6 hours
        //720,  // 12 hours
        1440, // 1 day
        //4320, // 3 days
        10080, // 1 week
        21600, // 3 weeks
        //43200, // 1 month
    ];

    /**
     * How long to cache OHLC responses (seconds).
     */
    private int $cacheTtl;

    public function __construct(
        protected Client $http = new Client([
            'base_uri' => 'https://api.kraken.com/0/public/',
            'verify'   => false,
        ]),
        int $cacheTtlSeconds = 60
    ) {
        $this->cacheTtl = $cacheTtlSeconds;
    }

    /**
     * Fetch OHLC bars for a given pair and interval.
     *
     * @param  string  $pair     Kraken pair identifier, e.g. "BTCUSD"
     * @param  int     $interval Interval in minutes (must be one of VALID_OHLC_INTERVALS)
     * @return array<int, array> Array of OHLC rows
     *
     * @throws InvalidArgumentException if the interval is unsupported
     * @throws RuntimeException         on Kraken API errors
     */
    public function ohlc(string $pair, int $interval = 15): array
    {
        // Validate interval
        if (! in_array($interval, self::VALID_OHLC_INTERVALS, true)) {
            throw new InvalidArgumentException("Interval {$interval}m not supported by Kraken");
        }

        $cacheKey = "kraken:ohlc:{$pair}:{$interval}";

        // Cache for $cacheTtl seconds to avoid hammering the API
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($pair, $interval) {
            $response = $this->request('OHLC', [
                'pair'     => $pair,
                'interval' => $interval,
            ]);

            if (! isset($response['result'][$pair]) || ! is_array($response['result'][$pair])) {
                // Unexpected shape
                throw new RuntimeException("Kraken returned no OHLC data for {$pair}@{$interval}m");
            }

            return $response['result'][$pair];
        });
    }

    /**
     * Low-level HTTP helper.
     *
     * @param  string $path  API path under base_uri, e.g. "OHLC"
     * @param  array  $query Query parameters
     * @return array         Decoded JSON
     *
     * @throws RuntimeException on Kraken error
     */
    private function request(string $path, array $query = []): array
    {
        $maxAttempts = 3;
        $attempt     = 0;

        do {
            try {
                $attempt++;

                $res  = $this->http->get($path, ['query' => $query]);
                $json = json_decode($res->getBody()->getContents(), true);

                if (! is_array($json) || ! empty($json['error'] ?? ['?'])) {
                    $error = json_encode($json['error'] ?? ['unknown error']);
                    throw new \RuntimeException("Kraken error: {$error}");
                }

                return $json;
            } catch (\Throwable $e) {
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException(
                        "Failed after {$attempt} attempts: " . $e->getMessage(),
                        0,
                        $e
                    );
                }
                // wait before retrying
                sleep(3);
            }
        } while ($attempt < $maxAttempts);

        // should never get here
        throw new \RuntimeException('Unexpected error in request retry loop');
    }
}
