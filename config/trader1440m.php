<?php
// config/trader1440m.php

return [

    // --- Returns calculation period (bars of 1440 min = 1 day each) ---
    // 10 bars ≈ 10 days total
    'ret_period'           => 10,

    // Price relative period
    // 14 bars ≈ 14 days for high/low relative calc
    'price_rel_period'     => 14,

    // Rate-of-change periods
    // 10 bars (10 days) for both price ROC and volume ROC
    'roc_period'           => 10,
    'vol_roc_period'       => 10,

    // --- Moving Averages (daily bars) ---
    // Fast EMA spans ~1 day, slow EMA spans ~3 days
    'ema_fast'             => 5,    // 1 day EMA
    'ema_slow'             => 15,    // 3 day EMA

    // Common EMA/SMMA lengths scaled for daily:
    'ema9_period'         => 9,   // 9-hour EMA
    'ema21_period'         => 21,   // 21-hour EMA
    'smma9_period'         => 9,   // 9-hour SMMA
    'smma21_period'         => 21,   // 21-hour SMMA
    'smma50_period'         => 50,   // 50-hour SMMA
    'smma200_period'        => 200,  // 200-hour SMMA

    // --- Momentum Indicators (daily bars) ---
    'rsi_period'           => 14,   // 14 day RSI
    'macd_fast'            => 12,   // MACD fast EMA (12 days)
    'macd_slow'            => 26,   // MACD slow EMA (26 days)
    'macd_signal'          => 9,    // MACD signal EMA (9 days)

    // --- Stochastic Oscillator (Fast) ---
    'stoch_fastk_period'   => 14,
    'stoch_fastd_period'   => 3,
    'stoch_slowk_period'   => 10,
    'stoch_slowk_ma_type'  => 0,
    'stoch_slowd_period'   => 3,
    'stoch_slowd_ma_type'  => 0,

    // --- Composite Oscillators ---
    'ult_1'                => 7,    // 7 day
    'ult_2'                => 14,   // 14 day
    'ult_3'                => 28,   // 28 day

    // --- Volatility & Volume (daily bars) ---
    'atr_period'           => 14,   // 14 day ATR
    'vol_roc_period'       => 10,   // 10 day volume ROC

    // --- Bollinger Bands (daily bars) ---
    'bbands_period'        => 20,   // 20 day BB period
    'bbands_stddev'        => 2.0,

    // --- Additional Indicators ---
    'cci_period'           => 20,   // 20 day CCI
    'adx_period'           => 14,   // 14 day ADX
];
