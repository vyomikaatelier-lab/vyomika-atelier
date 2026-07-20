<?php

namespace App\Support;

final class LeadProtectionStatus
{
    public const VERIFIED = 'verified';

    public const NEEDS_VERIFICATION = 'needs_verification';

    public const DUPLICATE = 'duplicate';

    public const MARKETING_VENDOR = 'marketing_vendor';

    public const SPAM_SUSPECTED = 'spam_suspected';

    public const BLOCKED = 'blocked';

    /** @return list<string> */
    public static function all(): array
    {
        return config('form_protection.protection_statuses', []);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::VERIFIED => 'Verified',
            self::NEEDS_VERIFICATION => 'Needs Verification',
            self::DUPLICATE => 'Duplicate',
            self::MARKETING_VENDOR => 'Marketing / Vendor',
            self::SPAM_SUSPECTED => 'Spam Suspected',
            self::BLOCKED => 'Blocked',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function notifyAdmin(string $status): bool
    {
        return ! in_array($status, [
            self::DUPLICATE,
            self::BLOCKED,
            self::SPAM_SUSPECTED,
        ], true);
    }
}
