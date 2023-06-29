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
        'host_id' => env('ASANPARDAKHT_WALLET_HOST_ID'),
        'url' => env(
            'ASANPARDAKHT_WALLET_URL',
            'https://thirdparty.dev.tasn.ir/exts/v1/'
        ).env('ASANPARDAKHT_WALLET_HOST_ID').'/1',
    ],
];
