<?php

return [
    'honeypot_field' => 'va_contact_url',

    'min_submission_seconds' => 3,

    'max_submission_age_seconds' => 7200,

    'duplicate_lookback_hours' => 24,

    'max_urls_in_message' => 3,

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'testing_bypass_token' => env('TURNSTILE_TESTING_BYPASS_TOKEN', 'test-turnstile-pass'),
        'skip_verification' => env('TURNSTILE_SKIP_VERIFICATION', false),
    ],

    'enquiry_intents' => [
        'active_project' => 'Active project (ready to order)',
        'planning_3_months' => 'Planning within 3 months',
        'planning_later' => 'Planning later / exploring options',
        'b2b_dealership' => 'B2B or dealership enquiry',
        'architect_contractor' => 'Architect / contractor project',
        'catalogue_only' => 'Catalogue / inspiration only',
        'general_enquiry' => 'General enquiry',
        'vendor_proposal' => 'Vendor or service proposal',
    ],

    'vendor_intent' => 'vendor_proposal',

    'protection_statuses' => [
        'verified',
        'needs_verification',
        'duplicate',
        'marketing_vendor',
        'spam_suspected',
        'blocked',
    ],

    'rate_limits' => [
        'general_enquiry' => ['max' => 3, 'decay_minutes' => 15],
        'professional_application' => ['max' => 2, 'decay_minutes' => 60],
        'catalogue_request' => ['max' => 3, 'decay_minutes' => 30],
        'railing_quote' => ['max' => 2, 'decay_minutes' => 30],
        'vendor_proposal' => ['max' => 2, 'decay_minutes' => 60],
        'dealer_application' => ['max' => 2, 'decay_minutes' => 60],
        'otp_send' => ['max' => 3, 'decay_minutes' => 60],
        'otp_verify' => ['max' => 5, 'decay_minutes' => 60],
        'file_upload_forms' => ['max' => 2, 'decay_minutes' => 30],
    ],

    'form_groups' => [
        'contact' => 'general_enquiry',
        'custom_order' => 'general_enquiry',
        'order_now' => 'file_upload_forms',
        'service_inquiry' => 'general_enquiry',
        'professional_application' => 'professional_application',
        'railing_quotation' => 'railing_quote',
        'catalogue_request' => 'catalogue_request',
        'vendor_proposal' => 'vendor_proposal',
        'dealer_application' => 'dealer_application',
        'account_register' => 'otp_send',
        'account_login_otp' => 'otp_send',
        'account_forgot_otp' => 'otp_send',
        'account_verify_otp' => 'otp_verify',
    ],

    'spam_phrases' => [
        'search engine optimization',
        'seo services',
        'digital marketing',
        'link building',
        'guest post',
        'increase your traffic',
        'web development services',
        'software development',
        'outsourcing',
        'promotional offer',
        'limited time offer',
        'click here',
        'buy now',
        'crypto',
        'forex',
        'casino',
        'viagra',
        'work from home',
        'make money fast',
    ],

    'disposable_email_domains' => [
        'mailinator.com',
        'guerrillamail.com',
        'guerrillamail.net',
        'tempmail.com',
        'temp-mail.org',
        '10minutemail.com',
        'throwaway.email',
        'yopmail.com',
        'sharklasers.com',
        'trashmail.com',
        'getnada.com',
        'maildrop.cc',
        'dispostable.com',
        'fakeinbox.com',
        'mintemail.com',
    ],

    'messages' => [
        'generic_reject' => 'We could not process your submission. Please try again in a moment.',
        'rate_limited' => 'Too many requests. Please wait a few minutes before trying again.',
    ],
];
