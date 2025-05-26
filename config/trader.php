<?php
// config/trader_60m.php

return [

    // Moving Averages (1-hour bars)
    'ema_fast'             => 3,     // ≈ 3 hours
    'ema_slow'             => 9,     // ≈ 9 hours
    'ema20_period'         => 10,    // ≈ 10 hours anchor (shorter for quicker adaption)
    'smma50_period'         => 14,    // ≈ 14 hours
    'smma200_period'        => 50,    // ≈ 2 days

    // Momentum Indicators
    'rsi_period'           => 10,    // 10-hour momentum window
    'macd_fast'            => 3,     
    'macd_slow'            => 8,
    'macd_signal'          => 3,

    // Stochastic Settings
    'stoch_fastk_period'   => 10,
    'stoch_slowk_period'   => 3,     // faster smoothing
    'stoch_slowk_ma_type'  => 0,     // SMA
    'stoch_slowd_period'   => 3,
    'stoch_slowd_ma_type'  => 0,     // SMA

    // Volatility & Volume
    'atr_period'           => 7,     // slightly faster ATR for 1-hour bars
    'vol_smma_period'       => 6,     // 6-hour rolling baseline

    // Bollinger Bands
    'bbands_period'        => 8,     // a little quicker to adapt
    'bbands_stddev'        => 2.0,   // slightly wider for 1h bars
    'bbands_volatility_threshold' => 2.5,

    // Spike Filter Thresholds
    'atr_spike_multiplier' => 1.7,   // slightly higher spike sensitivity
    'vol_spike_threshold'  => 1.0,   // normalized for larger volume swings

    // Candle-structure ratios
    'tiny_wick_ratio'      => 0.25,
    'upper_shadow_ratio'   => 0.5,

    // Other Indicators
    'cci_period'           => 12,
    'adx_period'           => 14,
];
