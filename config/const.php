<?php

return [
    'set' => [
        'company_code' => 'SKB', //must change in production
        'sbo_agent' => 'agentskb', //must change in production
        'internal_key' => 'wpUDvZGSYB4T87GgYNfi89cESpEuvDz7', //must change in production
        'api_url' => env('API_URL', 'https://api-f3.sukabet.co'),  //must change in production
        'gallery' => 'api-upload',  //must change in production. 'api-upload' for staging, 'api-u3' for production
        'ref_code' => 'ref_code',
        'webroot' => 'webroot', //CakePHP,
        'theme_color' => 'dark_orange',
        'theme_type' => 1, // 1 for gradient, 2 for flat
        'cdn' => 'https://latte.cdnsimple.top/', //might change it in production
        'cache' => 10, //10 minutes
        'google_analytics' => env('GOOGLE_ANALYTICS_PROPERTY_ID'), //testing
    ],
    'page' => [
        'home' => 'ph',
        'sport_book' => 'psb',
        'casino' => 'pcn',
        'poker' => 'ppk',
        'lottery' => 'plt',
        'game' => 'pgm',
        'promotion' => 'pbn',
        'reward' => 'prw',
        'register' => 'prg',
        'responsible' => 'prp',
        'abt' => 'pabt',
        'contact' => 'pct',
        'prl' => 'rule',
        'slot' => 'psl',
        'cock_fight' => 'pcf',
        'fishing' => 'pf',
        'deposit' => 'dps',
        'withdraw' => 'pw',
        'hot_game' => 'phg',
        'event' => 'prm',
        'setting' => 'setting'
    ],
    'file' => [ //page file name
        'page' => 'page',
        'slider' => 'slider',
        'bank' => 'bank',
        'contact' => 'contact',
        'promotion' => 'bonus',
        'event' => 'promotion',
        'reward' => 'reward'
    ],
    'provider_sk' => [ //provider code
        'sk' => 'sk',
        'baccarat' => 'baccarat',
        'holdem' => 'holdem',
        'big2' => 'big2',
        'ceme' => 'ceme',
        'susun' => 'susun',
        'isin' => 'isin',
        'sk4d' => 'sk4d'
    ],
    'provider_other' => [
        'pp' => 'pp',
        'pprkd' => 'pprkd',
    ],
    'provider_sbo' => [
        'sbo' => 'sbo',
    ],
    'provider_joker' => [
        'jks' => 'jks',
    ],
    'provider_saba' => [
        'saba' => 'saba',
    ],
    'provider_opk' => [
        'opk' => 'opk'
    ],
    'provider_cmd368' => [
        'cmd368' => 'cmd368'
    ],
    'provider_afb' => [
        'afb' => 'afb'
    ],
    'status' => [
        'active' => 1,
        'inactive' => 0,
        'approve' => 1,
        'reject' => 2,
        'request' => 0,
        'error' => 3,
        'auto' => 4,
        'deposit' => 1,
        'transfer' => 2,
        'withdraw' => 3
    ],
    'msg' => [
        'empty_data' => 'tidak ada data!',
        'insufficient_balance' => 'Balance tidak cukup.',
        'missing_api_key' => 'Missing api key.',
        'wrong_api_key' => 'API key misses match.',
        'invalid_captcha' => 'Captcha code is invalid!',
        'not_login' => 'Login is required.',
        'login_failed' => 'Login Failed!',
        'no_game' => 'Game not available.',
        'deposit_success' => 'Terima Kasih, deposit anda segera kami proseskan.',
        'withdraw_success' => 'Terima Kasih, withdraw anda segera kami proseskan.',
        'update_profile' => 'Your data have been updated successfully.',
        'request_success' => 'Request is successfully.',
        'claim_success' => 'Claim have been successfully!',
        'maintenance' => 'Game is under maintenance.',
        'wrong_data' => 'Wrong data',
    ]
];
