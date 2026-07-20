<?php

namespace App\Support;

final class DisposableEmailChecker
{
    public static function isDisposable(string $email): bool
    {
        $domain = strtolower((string) str($email)->after('@'));

        if ($domain === '') {
            return false;
        }

        return in_array($domain, config('form_protection.disposable_email_domains', []), true);
    }
}
