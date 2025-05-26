<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

final class KrakenBalance
{
    protected string $apiUrl;
    protected string $key;
    protected string $secret;

    public function __construct()
    {
        $this->apiUrl = config('kraken.api_url', 'https://api.kraken.com');
        $this->key    = config('kraken.balance.key');
        $this->secret = config('kraken.balance.secret');
    }

    /**
     * Get all asset balances from Kraken.
     *
     * @return array
     * @throws \Exception
     */
    public function getBalances(): array
    {
        $path  = '/0/private/Balance';
        $nonce = (string) now()->getPreciseTimestamp(3);

        $body = [
            'nonce' => $nonce,
        ];

        $postData = http_build_query($body, '', '&');

        $signature = $this->getSignature($path, $postData, $nonce);

        $response = Http::withHeaders([
            'API-Key'  => $this->key,
            'API-Sign' => $signature,
        ])
            ->withOptions([
                'curl'   => [
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_FORBID_REUSE  => true,
                ],
                'verify' => false
            ])
            ->asForm()->post($this->apiUrl . $path, $body);

        if ($response->failed()) {
            Log::error('Kraken Balance request failed', [
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            throw new \Exception("Kraken API returned HTTP {$response->status()}");
        }

        $payload = $response->json();

        if (! empty($payload['error'])) {
            Log::error('Kraken Balance API error', ['error' => $payload['error']]);
            throw new \Exception('Kraken API error: ' . implode('; ', $payload['error']));
        }

        return $payload['result'] ?? [];
    }

    /**
     * Sign request payload per Kraken spec.
     */
    protected function getSignature(string $path, string $postData, string $nonce): string
    {
        // Message = path + SHA256(nonce + POST data)
        $hash = hash('sha256', $nonce . $postData, true);
        $message = $path . $hash;

        // HMAC-SHA512 with base64-decoded secret
        $hmac = hash_hmac(
            'sha512',
            $message,
            base64_decode($this->secret, true),
            true
        );

        return base64_encode($hmac);
    }
}
