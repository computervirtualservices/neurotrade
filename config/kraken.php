<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kraken API Configuration
    |--------------------------------------------------------------------------
    |
    | Separate credentials for buy, sell, and balance-only operations, plus
    | the base API URL.
    |
    */
    'api_url' => env('KRAKEN_API_URL', 'https://api.kraken.com'),

    'buy' => [
        'key'    => env('KRAKEN_API_KEY_BUY'),
        'secret' => env('KRAKEN_API_SECRET_BUY'),
    ],

    'sell' => [
        'key'    => env('KRAKEN_API_KEY_SELL'),
        'secret' => env('KRAKEN_API_SECRET_SELL'),
    ],

    'balance' => [
        'key'    => env('KRAKEN_API_KEY_BALANCE'),
        'secret' => env('KRAKEN_API_SECRET_BALANCE'),
    ],
];
