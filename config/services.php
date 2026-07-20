<?php

return [
    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID', env('RAZORPAY_KEY')),
        'secret' => env('RAZORPAY_KEY_SECRET', env('RAZORPAY_SECRET')),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'admin_email' => env('ADMIN_EMAIL', 'admin@vyomikaatelier.com'),
];
