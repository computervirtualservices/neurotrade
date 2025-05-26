<?php
// config/trader30m.php
return [

    // Returns calculation period
    'ret_period'           => 10,   // period for log returns (e.g. 10 bars)

    // Price relative period
    'price_rel_period'     => 14,   // period for price relative high/low calculation

    // Rate-of-change periods
    'roc_period'           => 10,   // period for price ROC
    'vol_roc_period'       => 10,   // period for volume ROC   // period for price relat

    // Moving Averages (30-minute bars)
    'ema_fast'             => 5,    // 5 periods ≈ 2.5 hours
    'ema_slow'             => 15,   // 15 periods ≈ 7.5 hours
    'ema9_period'         => 9,   // 9-period EMA
    'ema21_period'         => 21,   // 21-period EMA
    'smma9_period'         => 9,   // 9-period SMMA
    'smma21_period'         => 21,   // newly added 21-period SMMA
    'smma50_period'         => 50,   // 50-period SMMA
    'smma200_period'        => 200,  // 200-period SMMA

    // Momentum Indicators
    'rsi_period'           => 14,   // 14-period RSI
    'macd_fast'            => 12,
    'macd_slow'            => 26,
    'macd_signal'          => 9,

    // Stochastic Oscillator (Fast)
    'stoch_fastk_period'   => 14,
    'stoch_fastd_period'   => 3,
    'stoch_slowk_period'   => 10,  // slow %K smoothing
    'stoch_slowk_ma_type'  => 0,   // SMA for slow %K
    'stoch_slowd_period'   => 3,   // slow %D smoothing
    'stoch_slowd_ma_type'  => 0,   // SMA for slow %D

    // Composite Oscillators
    'ult_1'                => 7,
    'ult_2'                => 14,
    'ult_3'                => 28,

    // Volatility & Volume
    'atr_period'           => 14,
    'vol_roc_period'       => 10,

    // Bollinger Bands
    'bbands_period'        => 20,
    'bbands_stddev'        => 2.0,

    // Additional Indicators
    'cci_period'           => 20,
    'adx_period'           => 14,
];
