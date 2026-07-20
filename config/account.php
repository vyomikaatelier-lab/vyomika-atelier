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
        '+91' => 'India (+91)',
        '+971' => 'UAE (+971)',
        '+1' => 'USA (+1)',
        '+44' => 'UK (+44)',
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
