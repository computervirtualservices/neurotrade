<?php

namespace App\Models;

use App\Services\KrakenTicker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssetPair extends Model
{
    // If your table name doesn’t follow the plural‑of‑class convention,
    // uncomment and customize the line below:
    // protected $table = 'asset_pairs';

    protected $primaryKey = 'id';

    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'pair_name',
        'alt_name',
        'ws_name',
        'base_currency',
        'quote_currency',
        'aclass_base',
        'aclass_quote',
        'lot',
        'cost_decimals',
        'pair_decimals',
        'lot_decimals',
        'lot_multiplier',
        'leverage_buy',
        'leverage_sell',
        'fees',
        'fees_maker',
        'fee_volume_currency',
        'margin_call',
        'margin_stop',
        'ordermin',
        'costmin',
        'tick_size',
        'interval',
        'status',
        'is_watchlisted',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // JSON fields → arrays
        'leverage_buy'    => 'array',
        'leverage_sell'   => 'array',
        'fees'            => 'array',
        'fees_maker'      => 'array',

        // Numeric fields
        'cost_decimals'   => 'integer',
        'pair_decimals'   => 'integer',
        'lot_decimals'    => 'integer',
        'lot_multiplier'  => 'integer',
        'margin_call'     => 'integer',
        'margin_stop'     => 'integer',

        // Boolean
        'is_watchlisted'  => 'boolean',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'is_watchlisted' => false,
    ];

    /**
     * Optional: 
     * If you need to hide any attributes when the model is serialized to JSON,
     * add them here.
     */
    // protected $hidden = [
    //     'id',
    // ];

    /**
     * Optional: 
     * If you frequently query by status or watchlist, you can
     * add local scopes for convenience.
     */
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeWatchlisted($query)
    {
        return $query->where('is_watchlisted', true);
    }

    /**
     * Fetch the latest ask-prices for a set of symbols from Kraken.
     *
     * @param  \Illuminate\Support\Collection|array  $symbols
     * @return array  [ 'PAIRNAME' => askPriceFloat, … ]
     */
    protected static function fetchAskPrices(array $symbols): array
    {
        // Try up to 3 times, waiting 100ms between attempts, only retry on connection / timeout errors
        $response = Http::retry(
            3,
            100,
            function ($exception, $request) {
                return $exception instanceof ConnectionException
                    || $exception instanceof RequestException;
            }
        )
            ->withOptions(['verify' => false])
            ->get('https://api.kraken.com/0/public/Ticker', [
                'pair' => implode(',', $symbols),
            ]);

        // If it still failed after retries, bail out with empty array
        if (! $response->successful()) {
            Log::warning('fetchAskPrices: all retries failed for ' . implode(',', $symbols));
            return [];
        }

        $tickers = $response->json('result', []);

        return collect($tickers)
            ->mapWithKeys(fn($data, $symbol) => [
                $symbol => isset($data['a'][0])
                    ? (float) $data['a'][0]
                    : null,
            ])
            ->all();
    }

    public static function getWsNameOrPairName(string $pair): ?string
    {
        return self::query()
            ->where('pair_name', $pair)
            ->orWhere('alt_name', $pair)
            ->orWhere('ws_name', $pair)
            ->value('ws_name');
    }


    public static function getAssetPairBySymbolLotSize(string $pair): int
    {
        return self::query()
            ->where('pair_name', $pair)
            ->orWhere('alt_name', $pair)
            ->orWhere('ws_name', $pair)
            ->value('lot_decimals');
    }

    public static function getAssetPairBySymbolBaseCurrency(string $pair): int
    {
        return self::query()
            ->where('pair_name', $pair)
            ->orWhere('alt_name', $pair)
            ->orWhere('ws_name', $pair)
            ->value('base_currency');
    }

    /**
     * Fetch the latest ask-prices for a set of symbols from Kraken.
     *
     * @param  \Illuminate\Support\Collection|array  $symbols
     * @return array  [ 'PAIRNAME' => askPriceFloat, … ]
     */
    protected static function fetchBidPrices(array $symbols): array
    {
        // Try up to 3 times, waiting 100ms between attempts, only retry on connection / timeout errors
        $response = Http::retry(
            3,
            100,
            function ($exception, $request) {
                return $exception instanceof ConnectionException
                    || $exception instanceof RequestException;
            }
        )
            ->withOptions(['verify' => false])
            ->get('https://api.kraken.com/0/public/Ticker', [
                'pair' => implode(',', $symbols),
            ]);

        // If it still failed after retries, bail out with empty array
        if (! $response->successful()) {
            Log::warning('fetchBidPrices: all retries failed for ' . implode(',', $symbols));
            return [];
        }

        $tickers = $response->json('result', []);

        return collect($tickers)
            ->mapWithKeys(fn($data, $symbol) => [
                $symbol => isset($data['b'][0])
                    ? (float) $data['b'][0]
                    : null,
            ])
            ->all();
    }


    /**
     * Convert an arbitrary asset amount into ZUSD using Kraken ticker data.
     *
     * @param  string  $fromAsset  e.g. "EUR", "USD", "ETH"
     * @param  float   $amount     how many units of $fromAsset
     * @param  array   $tickerData full Kraken ticker "result"
     * @return float|null          equivalent ZUSD amount, or null if no route
     */
    public static function convertToZUSD(string $fromAsset, float $amount, array $tickerData): ?float
    {
        $from   = strtoupper($fromAsset);
        $pair1  = $from . 'USD'; // e.g. EURZUSD
        $pair2  = 'USD' . $from; // e.g. ZUSDEUR

        // If the asset *is* USD, it’s already USD
        if ($from === 'USD') {
            return $amount;
        }

        // If there’s a direct pair FROM→ZUSD, use the bid price (you get ZUSD for selling FROM)
        if (isset($tickerData[$pair1])) {
            $bid = floatval($tickerData[$pair1]['b'][0]);
            return $amount * $bid;
        }

        // If there’s a reverse pair ZUSD→FROM, use the ask price (you pay ZUSD to buy FROM)
        if (isset($tickerData[$pair2])) {
            $ask = floatval($tickerData[$pair2]['a'][0]);
            return $ask > 0 ? $amount / $ask : null;
        }

        return null;
    }

    /**
     * Return a Collection of watch-listed AssetPairs, each with a →price in ZUSD.
     *
     * @return \Illuminate\Support\Collection<static>
     */
    public static function getWatchlisted(int $interval): \Illuminate\Support\Collection
    {
        // 1) Pull every pair the user has flagged as “watchlisted”
        $pairs = static::watchlisted()->where('interval', $interval)->get();

        // Short-circuit if there is nothing to enrich
        if ($pairs->isEmpty()) {
            return collect();
        }

        // 2) Grab the freshest ticker snapshot from Kraken (cache for 20 s to spare the API)
        $tickerData = cache()->remember(
            'kraken:ticker:snapshot',
            now()->addSeconds(20),
            fn() => app(KrakenTicker::class)->fetchTickerData()['result'] ?? []
        );

        // 3) Walk each pair and inject its ZUSD price
        return $pairs->map(function (self $pair) use ($tickerData) {
            try {
                // a. Pull the best ask (data_get swallows “index undefined” issues)
                $ask = (float) data_get($tickerData, "{$pair->pair_name}.a.0");

                // b. Convert to ZUSD if we got a quote; otherwise leave null
                $pair->price = $ask
                    ? static::convertToZUSD($pair->quote_currency, $ask, $tickerData)
                    : null;
            } catch (\Throwable $e) {
                Log::warning("Watchlist price calc failed for {$pair->pair_name}: {$e->getMessage()}");
                $pair->price = null;
            }

            return $pair;
        });
    }


    /**
     * Return a Collection of watchlisted AssetPairs, each with a ->price in ZUSD.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getWatchlistedWithPrice(): \Illuminate\Support\Collection
    {
        // 1) Retrieve all watchlisted trading pairs
        $pairs = static::watchlisted()->get();

        // 2) Fetch current ticker data from Kraken API
        /** @var KrakenService $kraken */
        $kraken = app(KrakenTicker::class);
        $rawTicker = $kraken->fetchTickerData();
        $tickerData = $rawTicker['result'] ?? [];

        // 3) Map each pair and inject the current price in ZUSD
        return $pairs->map(function (self $pair) use ($tickerData) {
            try {
                // Extract the ask price for this pair if available
                $ask = isset($tickerData[$pair->pair_name]['a'][0])
                    ? (float) $tickerData[$pair->pair_name]['a'][0]
                    : null;

                // Convert the ask price to ZUSD
                $pair->price = $ask !== null
                    ? static::convertToZUSD($pair->quote_currency, $ask, $tickerData)
                    : null;

                return $pair;
            } catch (\Exception $e) {
                // Log the error and return pair without price
                Log::error("Error calculating price for {$pair->pair_name}: " . $e->getMessage());
                $pair->price = null;
                return $pair;
            }
        });
    }

    /**
     * Fetch the latest ask price for this pair and return it in ZUSD.
     *
     * @return float|null  Latest price in ZUSD, or null on failure.
     */
    public function getLatestPrice(): ?float
    {
        try {
            // 1) Fetch the full ticker data for this symbol
            /** @var KrakenService $kraken */
            $kraken     = app(KrakenTicker::class);
            $rawResult  = $kraken->fetchTickerData();
            $tickerData = $rawResult['result'] ?? [];

            // 2) Grab the ask price (first element of 'a')
            if (! isset($tickerData[$this->pair_name]['a'][0])) {
                return null;
            }
            $ask = (float) $tickerData[$this->pair_name]['a'][0];

            // 3) If the quote currency is already ZUSD, return the ask directly
            if (strtoupper($this->quote_currency) === 'ZUSD') {
                return $ask;
            }

            // 4) Otherwise convert from quote → ZUSD
            return self::convertToZUSD(
                $this->quote_currency,
                $ask,
                $tickerData
            );
        } catch (\Throwable $e) {
            Log::error("Error fetching latest price for {$this->pair_name}: {$e->getMessage()}");
            return null;
        }
    }
}
