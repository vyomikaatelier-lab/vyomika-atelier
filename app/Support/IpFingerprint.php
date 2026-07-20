<?php

namespace App\Support;

final class IpFingerprint
{
    public static function hash(?string $ip): ?string
    {
        if (! filled($ip)) {
            return null;
        }

        $salt = config('lead_qualification.ip_hash_salt') ?: config('app.key');

        return hash_hmac('sha256', $ip, (string) $salt);
    }
}
