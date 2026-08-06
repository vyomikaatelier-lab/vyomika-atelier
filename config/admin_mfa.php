<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin MFA (TOTP)
    |--------------------------------------------------------------------------
    |
    | Existing admins receive a grace window after migration. New admins should
    | enroll immediately (grace_ends_at = now). Secrets are encrypted at rest.
    |
    */
    'grace_days' => (int) env('ADMIN_MFA_GRACE_DAYS', 7),
    'issuer' => env('ADMIN_MFA_ISSUER', env('APP_NAME', 'Vyomika Atelier')),
    'window' => (int) env('ADMIN_MFA_WINDOW', 1),
];
