<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    // Routes that require CORS
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
    ],

    'allowed_methods' => ['*'],

    // Replace '*' with your exact Nuxt3 frontend domain
    'allowed_origins' => [
        'https://nuxt3-sanctum-production-f4df.up.railway.app',
    ],

    // Optional: allow subdomains via regex
    'allowed_origins_patterns' => [
        // '/\.up\.railway\.app$/', // if you need wildcard subdomains
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for cookie-based auth
    'supports_credentials' => true,
];
