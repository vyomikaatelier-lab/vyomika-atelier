<?php

return [
    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID', env('RAZORPAY_KEY')),
        'secret' => env('RAZORPAY_KEY_SECRET', env('RAZORPAY_SECRET')),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'admin_email' => env('ADMIN_EMAIL', 'admin@vyomikaatelier.com'),

    'marketing_email' => env('MARKETING_EMAIL'),

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/account/auth/google/callback'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', env('APP_URL') . '/account/auth/apple/callback'),
        'key_id' => env('APPLE_KEY_ID'),
        'team_id' => env('APPLE_TEAM_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],
];
