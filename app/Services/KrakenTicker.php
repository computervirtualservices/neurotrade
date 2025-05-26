<?php

namespace App\Services;

use Illuminate\Support\Facades\Http; // Utilize Laravel's HTTP client
use Illuminate\Support\Facades\Log;

/**
 * KrakenService
 *
 * Handles fetching and parsing data from Kraken's public ticker API.
 */
class KrakenTicker
{
    /**
     * Fetch data from Kraken's ticker API.
     *
     * @return array Parsed JSON response as an associative array.
     */
    public function fetchTickerData(?string $pairName = null): array
    {
        $url = 'https://api.kraken.com/0/public/Ticker';

        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $attempt++;

                // Make the request (SSL verify false only for dev)
                if ($pairName === null) {
                    $response = Http::withHeaders([
                        'Cache-Control' => 'no-cache',      // <-- disable HTTP caching
                    ])
                        ->withOptions([
                            'curl'   => [
                                CURLOPT_FRESH_CONNECT => true,
                                CURLOPT_FORBID_REUSE  => true,
                            ],
                            'verify' => false
                        ])->get($url);
                } else {
                    $response = Http::withHeaders([
                        'Cache-Control' => 'no-cache',      // <-- disable HTTP caching
                    ])
                        ->withOptions([
                            'curl'   => [
                                CURLOPT_FRESH_CONNECT => true,
                                CURLOPT_FORBID_REUSE  => true,
                            ],
                            'verify' => false
                        ])->get($url, [
                            'pair' => $pairName,
                        ]);
                }
                
                // If the response is OK and has JSON, return it
                if ($response->ok()) {
                    return $response->json();
                }

                // If response not OK, throw to catch block
                throw new \Exception("Unexpected status: {$response->status()}");
            } catch (\Throwable $e) {
                // Optional: log error or retry message
                Log::warning("Kraken API fetch attempt #{$attempt} failed: " . $e->getMessage());

                // Small delay between retries
                usleep(200000); // 200ms
            }
        }

        // All retries failed — return fallback
        return ['error' => 'Failed to fetch data from Kraken API after 3 attempts.'];
    }
}
