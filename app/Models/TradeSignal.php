<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeSignal extends Model
{
    use HasFactory;

    // mass-assignable columns (guard the rest)
    protected $fillable = [
        'pair_name',
        'interval',
        'signal',
        'confidence',
        'action',
        'strength',
        'confidence_percent',
        'confidence_level',
        'explanation',
        'suggested_entry',
        'suggested_stop_loss',
        'suggested_take_profit',
        'buy_price',
        'sell_price',
        'support_levels',
        'resistance_levels',
        'key_indicators',
    ];

    // type-casting: converts JSON columns to arrays, decimals to floats
    protected $casts = [
        'confidence'             => 'float',
        'confidence_percent'     => 'float',
        'suggested_entry'        => 'float',
        'suggested_stop_loss'    => 'float',
        'suggested_take_profit'  => 'float',
        'support_levels'         => 'array',
        'resistance_levels'      => 'array',
        'key_indicators'         => 'array',
    ];
}
