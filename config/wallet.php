<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wallet payment component`s operation mode
    |--------------------------------------------------------------------------
    |
    | *** very important config ***
    |
    | production: component operates with real wallet payments
    | development/staging: component operates with simulated "wallet Test"
    |
    */

    'mode' => env('WALLET_MODE', 'production'),

    'asanpardakht' => [
        'debug' => true,
        'host_id' => '',
        'url' => '',
        'private_key' => '',
    ]

];
