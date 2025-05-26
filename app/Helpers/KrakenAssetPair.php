<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AssetPair;

final class KrakenAssetPair
{
    /**
     * Fetch all asset pairs from Kraken and upsert into the database.
     *
     * @return bool  True on success, false on failure.
     */
    public static function refresh(): bool
    {
        try {
            $response = Http::withOptions(['verify' => false])
                            ->get('https://api.kraken.com/0/public/AssetPairs');

            if (! $response->successful()) {
                Log::error('Failed to fetch data from Kraken API', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();

            if (! isset($data['result']) || ! is_array($data['result'])) {
                Log::error('Invalid response structure from Kraken API', ['payload' => $data]);
                return false;
            }

            foreach ($data['result'] as $pairName => $pairInfo) {
                // parse base/quote
                $baseCurrency  = '';
                $quoteCurrency = '';

                if (! empty($pairInfo['wsname'])) {
                    [$baseCurrency, $quoteCurrency] = explode('/', $pairInfo['wsname']);
                } else {
                    // fallback parsing
                    $commonQuotes = ['XBT','BTC','ETH','USD','EUR','JPY','GBP','CAD','AUD'];
                    foreach ($commonQuotes as $q) {
                        if (str_ends_with($pairName, $q)) {
                            $baseCurrency  = substr($pairName, 0, -strlen($q));
                            $quoteCurrency = $q;
                            break;
                        }
                    }
                }

                AssetPair::updateOrCreate(
                    ['pair_name' => $pairName],
                    [
                        'alt_name'           => $pairInfo['altname']             ?? null,
                        'ws_name'            => $pairInfo['wsname']              ?? null,
                        'base_currency'      => $baseCurrency,
                        'quote_currency'     => $quoteCurrency,
                        'aclass_base'        => $pairInfo['aclass_base']         ?? null,
                        'aclass_quote'       => $pairInfo['aclass_quote']        ?? null,
                        'lot'                => $pairInfo['lot']                ?? null,
                        'cost_decimals'      => $pairInfo['cost_decimals']      ?? null,
                        'pair_decimals'      => $pairInfo['pair_decimals']      ?? null,
                        'lot_decimals'       => $pairInfo['lot_decimals']       ?? null,
                        'lot_multiplier'     => $pairInfo['lot_multiplier']     ?? null,
                        'leverage_buy'       => isset($pairInfo['leverage_buy'])   ? json_encode($pairInfo['leverage_buy'])   : null,
                        'leverage_sell'      => isset($pairInfo['leverage_sell'])  ? json_encode($pairInfo['leverage_sell'])  : null,
                        'fees'               => isset($pairInfo['fees'])          ? json_encode($pairInfo['fees'])          : null,
                        'fees_maker'         => isset($pairInfo['fees_maker'])    ? json_encode($pairInfo['fees_maker'])    : null,
                        'fee_volume_currency'=> $pairInfo['fee_volume_currency'] ?? null,
                        'margin_call'        => $pairInfo['margin_call']         ?? null,
                        'margin_stop'        => $pairInfo['margin_stop']         ?? null,
                        'ordermin'           => $pairInfo['ordermin']            ?? null,
                        'costmin'            => $pairInfo['costmin']             ?? null,
                        'tick_size'          => $pairInfo['tick_size']           ?? null,
                        'status'             => $pairInfo['status']              ?? null,
                    ]
                );
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Error refreshing asset pairs from Kraken', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
