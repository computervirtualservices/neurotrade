<?php
// config/trader60m.php
return [

    // Returns calculation period
    // e.g. log returns over the last 10 hours
    'ret_period'           => 10,

    // Price relative period (hours)
    'price_rel_period'     => 14,

    // Rate-of-change periods (hours)
    'roc_period'           => 10,
    'vol_roc_period'       => 10,

    // Moving Averages (60-minute bars)
    // Fast/slow EMA for trend detection
    'ema_fast'             => 5,    // ~5 hours
    'ema_slow'             => 15,   // ~15 hours

    // Common EMAs & SMMAs
    'ema9_period'         => 9,   // 9-hour EMA
    'ema21_period'         => 21,   // 21-hour EMA
    'smma9_period'         => 9,   // 9-hour SMMA
    'smma21_period'         => 21,   // 21-hour SMMA
    'smma50_period'         => 50,   // 50-hour SMMA
    'smma200_period'        => 200,  // 200-hour SMMA

    // Momentum Indicators
    'rsi_period'           => 14,   // 14-hour RSI
    'macd_fast'            => 12,   // MACD fast EMA (12h)
    'macd_slow'            => 26,   // MACD slow EMA (26h)
    'macd_signal'          => 9,    // MACD signal line (9h)

    // Stochastic Oscillator (Fast)
    'stoch_fastk_period'   => 14,
    'stoch_fastd_period'   => 3,
    'stoch_slowk_period'   => 10,
    'stoch_slowk_ma_type'  => 0,
    'stoch_slowd_period'   => 3,
    'stoch_slowd_ma_type'  => 0,

    // Composite Oscillators
    'ult_1'                => 7,
    'ult_2'                => 14,
    'ult_3'                => 28,

    // Volatility & Volume
    'atr_period'           => 14,   // 14-hour ATR
    'vol_roc_period'       => 10,   // 10-hour volume ROC

    // Bollinger Bands
    'bbands_period'        => 20,   // 20-hour BB period
    'bbands_stddev'        => 2.0,  // ±2σ

    // Additional Indicators
    'cci_period'           => 20,   // 20-hour CCI
    'adx_period'           => 14,   // 14-hour ADX

];
