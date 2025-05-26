<?php
// config/cryptoml.php
return [

    /*
    |--------------------------------------------------------------------------
    | Rubix Model Persistence Path
    |--------------------------------------------------------------------------
    |
    | Where Rubix will store/load the serialized model.
    |
    */
    'model_path' => env('RUBIX_MODEL_PATH', 'private/ml_models/crypto_predictor.rbx'),

    /*
    |--------------------------------------------------------------------------
    | Minimum confidence threshold (0..1)
    |--------------------------------------------------------------------------
    |
    | Predictions below this threshold × 0.7 will be forced to NEUTRAL.
    |
    */
    'confidence_threshold' => (float) env('RUBIX_CONFIDENCE_THRESHOLD', 0.65),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL for feature importance (in minutes)
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => (int) env('RUBIX_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Is the model a regressor?
    |--------------------------------------------------------------------------
    */
    'is_regressor' => (bool) env('RUBIX_IS_REGRESSOR', true), // true or false

];
