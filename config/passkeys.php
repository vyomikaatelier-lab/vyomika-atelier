<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Relying Party ID
    |--------------------------------------------------------------------------
    |
    | WebAuthn passkeys are bound to this domain. Must match the site hostname
    | served over HTTPS in production (no port, no scheme).
    |
    */

    'relying_party_id' => env('PASSKEY_RP_ID', parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Relying Party Name
    |--------------------------------------------------------------------------
    |
    | Human-readable name shown by authenticators during registration.
    |
    */

    'relying_party_name' => env('PASSKEY_RP_NAME', config('app.name', 'Vyomika Atelier')),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | HTTPS origins permitted to complete WebAuthn ceremonies. Reject any
    | browser-reported origin not in this list.
    |
    */

    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', (string) env('PASSKEY_ORIGINS', config('app.url')))
    ))),

    /*
    |--------------------------------------------------------------------------
    | User Handle Secret
    |--------------------------------------------------------------------------
    */

    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),

    'timeout' => (int) env('PASSKEY_TIMEOUT', 60000),

    'guard' => 'web',

    'middleware' => ['web'],

    /*
    | Admin passkey management uses password + TOTP in App\Http\Controllers\Admin\PasskeyController.
    */
    'management_middleware' => [],

    /*
    | Throttling is applied explicitly on admin passkey routes via the admin-passkey limiter.
    */
    'throttle' => null,

    'redirect' => '/admin',

];
