<?php
// config/trader240m.php

return [

    // --- Returns calculation period (bars of 240 min = 4 h each) ---
    // 10 bars ≈ 40 hours total
    'ret_period'           => 10,

    // Price relative period
    // 14 bars ≈ 56 hours for high/low relative calc
    'price_rel_period'     => 14,

    // Rate-of-change periods
    // 10 bars (40 h) for both price ROC and volume ROC
    'roc_period'           => 10,
    'vol_roc_period'       => 10,

    // --- Moving Averages (240-minute bars) ---
    // Fast EMA spans ~4 h, slow EMA spans ~12 h
    'ema_fast'             => 5,    // 1×4 h EMA
    'ema_slow'             => 15,    // 3×4 h EMA

    // Common EMA/SMMA lengths scaled from 30 m config:
    'ema9_period'         => 9,   // 9-hour EMA
    'ema21_period'         => 21,   // 21-hour EMA
    'smma9_period'         => 9,   // 9-hour SMMA
    'smma21_period'         => 21,   // 21-hour SMMA
    'smma50_period'         => 50,   // 50-hour SMMA
    'smma200_period'        => 200,  // 200-hour SMMA

    // --- Momentum Indicators ---
    'rsi_period'           => 14,   // 14 bars → 56 h RSI
    'macd_fast'            => 12,
    'macd_slow'            => 26,
    'macd_signal'          => 9,

    // --- Stochastic Oscillator (Fast) ---
    'stoch_fastk_period'   => 14,
    'stoch_fastd_period'   => 3,
    'stoch_slowk_period'   => 10,
    'stoch_slowk_ma_type'  => 0,
    'stoch_slowd_period'   => 3,
    'stoch_slowd_ma_type'  => 0,

    // --- Composite Oscillators ---
    'ult_1'                => 7,
    'ult_2'                => 14,
    'ult_3'                => 28,

    // --- Volatility & Volume ---
    'atr_period'           => 14,
    'vol_roc_period'       => 10,

    // --- Bollinger Bands ---
    'bbands_period'        => 20,
    'bbands_stddev'        => 2.0,

    // --- Additional Indicators ---
    'cci_period'           => 20,
    'adx_period'           => 14,
];
