<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OhlcvData extends Model
{
    protected $table = 'ohlcv_data';

    protected $fillable = [
        'pair',
        'interval',
        'timestamp',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'vwap',
        'volume',
        'trade_count',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'open_price' => 'float',
        'high_price' => 'float',
        'low_price' => 'float',
        'close_price' => 'float',
        'volume' => 'float',
    ];

    public static function fetchOHLCData(string $pair, int $interval, int $limit = 50000, string $order = 'asc')
    {
        $wsName = AssetPair::getWsNameOrPairName($pair);

        $tableName = (string) Str::of($pair)
                    ->lower()
                    ->replace('/', '_')
                    ->append('_ohlcv');

        $collection = DB::table($tableName)->where('pair', $wsName)
        ->where('interval', $interval)
        ->orderBy('timestamp', $order)
        ->limit($limit)
        ->get()
        ->map(function ($row) {
            return [
                $row->timestamp, // 0 - Unix timestamp
                number_format($row->open_price, 8, '.', ''),      // 1 - Open
                number_format($row->high_price, 8, '.', ''),      // 2 - High
                number_format($row->low_price, 8, '.', ''),       // 3 - Low
                number_format($row->close_price, 8, '.', ''),     // 4 - Close
                number_format($row->vwap, 8, '.', ''),     // 5 - VWAP
                number_format($row->volume, 8, '.', ''),          // 6 - Volume
                (int) $row->trade_count,                          // 7 - Trade count
            ];
        });

        return $collection;
    }
}
