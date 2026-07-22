<?php

/**
 * WhatsApp OTP provider configuration.
 *
 * MSG91 template setup:
 * 1. Create an Authentication template named per MSG91_WHATSAPP_TEMPLATE_NAME (e.g. vyomika_otp).
 * 2. Body: "Your Vyomika Atelier verification code is {{1}}. Valid for 5 minutes."
 * 3. Add a copy-code or URL button parameter if your template requires it.
 * 4. Approve the template and note the namespace from MSG91.
 *
 * Meta template setup (when using driver=meta):
 * 1. Create an Authentication template named per WHATSAPP_OTP_TEMPLATE_NAME (e.g. vyomika_otp).
 * 2. Body: "Your Vyomika Atelier verification code is {{1}}. Valid for 5 minutes."
 * 3. Add a copy-code or URL button parameter if your template requires it.
 * 4. Approve the template before production use.
 */
return [
    'driver' => env('WHATSAPP_DRIVER', 'msg91'),

    'msg91' => [
        'auth_key' => env('MSG91_AUTH_KEY'),
        'integrated_number' => env('MSG91_WHATSAPP_INTEGRATED_NUMBER'),
        'template_name' => env('MSG91_WHATSAPP_TEMPLATE_NAME', 'vyomika_otp'),
        'template_namespace' => env('MSG91_WHATSAPP_TEMPLATE_NAMESPACE'),
        'template_language' => env('MSG91_WHATSAPP_TEMPLATE_LANGUAGE', 'en'),
    ],

    'meta' => [
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'otp_template_name' => env('WHATSAPP_OTP_TEMPLATE_NAME', 'vyomika_otp'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],
];
