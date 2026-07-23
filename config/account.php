<?php

return [
    'account_types' => [
        'customer' => 'Customer',
        'interior_designer' => 'Interior Designer',
        'architect' => 'Architect',
        'contractor' => 'Contractor',
        'dealer' => 'Dealer',
    ],

    'country_codes' => [
        '+91' => ['name' => 'India', 'iso' => 'IN'],
        '+971' => ['name' => 'UAE', 'iso' => 'AE'],
        '+1' => ['name' => 'USA', 'iso' => 'US'],
        '+44' => ['name' => 'UK', 'iso' => 'GB'],
    ],

    'otp' => [
        'length' => 6,
        'expiry_minutes' => 5,
        'resend_delay_seconds' => 30,
        'max_verification_attempts' => 5,
        'max_sends_per_hour' => 3,
    ],

    'copy' => [
        'login_title' => 'Welcome Back',
        'register_title' => 'Create Your Account',
        'send_otp' => 'Send WhatsApp OTP',
        'verify_otp' => 'Verify & Continue',
        'resend_otp' => 'Resend OTP',
        'success' => 'Your WhatsApp number has been verified successfully.',
        'failure' => 'The code is incorrect or has expired. Please request a new OTP.',
        'otp_sent_generic' => 'If this number is eligible, a verification code has been sent to your WhatsApp.',
    ],
];
