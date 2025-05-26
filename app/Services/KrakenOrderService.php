<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class KrakenOrderService
{
    protected Client $http;
    protected string $apiUrl;
    protected string $apiKey;
    protected string $apiSecret;

    public function __construct()
    {
        $this->apiUrl    = config('kraken.api_url');
        $this->apiKey    = config('kraken.buy.key');     // or sell.key depending
        $this->apiSecret = config('kraken.buy.secret');
        $this->http      = new Client([
            'base_uri' => $this->apiUrl,
            'timeout'  => 10,
        ]);
    }

    /**
     * Place a post-only limit order that expires after 5 seconds.
     *
     * @param  string  $pair      Kraken pair name, e.g. "BTC/USD"
     * @param  string  $type      "buy" or "sell"
     * @param  float   $volume    Order volume
     * @param  float   $price     Limit price
     * @return array              Kraken JSON response decoded
     */
    public function addPostOnlyLimitOrder(
        string $pair,
        string $type,
        float  $volume,
        float  $price
    ): array {
        $path  = '/0/private/AddOrder';
        $nonce = $this->getNonce();

        // 1) First try: post-only, GTD+5
        $body = [
            'nonce'       => $nonce,
            'ordertype'   => 'limit',
            'type'        => $type,
            'pair'        => $pair,
            'price'       => (string) $price,
            'volume'      => (string) $volume,
            'timeinforce' => 'GTD',
            'expiretm'    => '0',
        ];

        $response = $this->krakenRequest($path, $body);

        // 2) Handle post-only rejection or expiry
        if (! empty($response['error'])) {
            foreach ($response['error'] as $err) {
                $lower = strtolower($err);

                // if Post-Only maker-only rejection
                if (str_contains($lower, 'post only')) {
                    Log::warning("Post-only rejected for {$pair}@{$price}, retrying as plain limit");
                }
                // if the GTD window expired before fill
                elseif (str_contains($lower, 'expired')) {
                    Log::warning("Order expired for {$pair}@{$price}, retrying as standing limit");
                } else {
                    // some other error—bubble up
                    return $response;
                }

                // retry without post-only flags and expire
                unset($body['timeinforce'], $body['expiretm']);
                return $this->krakenRequest($path, $body);
            }
        }

        return $response;
    }

    protected function krakenRequest(string $path, array $body): array
    {
        $signature = $this->sign($path, $body['nonce'], $body);

        $res = Http::withHeaders([
            'API-Key'  => $this->apiKey,
            'API-Sign' => $signature,
        ])
            ->withOptions([
                'curl'   => [
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_FORBID_REUSE  => true,
                ],
                'verify' => false,
            ])
            ->asForm()
            ->post($this->apiUrl . $path, $body);

        // throw on HTTP-level errors
        if ($res->failed()) {
            throw new \RuntimeException("HTTP {$res->status()} calling Kraken");
        }

        return $res->json();  // ['error'=>[], 'result'=>…]
    }


    protected function getNonce(): string
    {
        // milliseconds since epoch
        return (string) round(microtime(true) * 1000);
    }

    protected function sign(string $path, string $nonce, array $body): string
    {
        // message: path + SHA256(nonce + POST data)
        $postData = http_build_query($body, '', '&');
        $sha256    = hash('sha256', $nonce . $postData, true);
        $message   = $path . $sha256;
        $secret    = base64_decode($this->apiSecret);

        $sig = hash_hmac('sha512', $message, $secret, true);
        return base64_encode($sig);
    }
}
