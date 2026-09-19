<?php

namespace Tests\Support;

/**
 * Safety checks for the opt-in MySQL/MariaDB invitation uniqueness harness.
 *
 * Never point this at production. Only MYSQL_TEST_* values are considered;
 * application DB_* credentials are not used.
 */
final class MysqlInvitationHarnessGuard
{
    /** @var list<string> */
    public const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /** @var list<string> */
    public const PRODUCTION_STYLE_DATABASES = [
        'vyomika_atelier',
        'vyomikaatelier',
        'production',
        'prod',
        'live',
        'mysql',
        'information_schema',
    ];

    public static function isEnabled(mixed $flag): bool
    {
        return $flag === '1';
    }

    /**
     * @return list<string>
     */
    public static function refusalReasons(?string $host, ?string $database, ?string $applicationDatabase): array
    {
        $reasons = [];
        $host = strtolower(trim((string) $host));
        $database = strtolower(trim((string) $database));
        $applicationDatabase = strtolower(trim((string) $applicationDatabase));

        if ($host === '' || ! in_array($host, self::LOCAL_HOSTS, true)) {
            $reasons[] = 'host must be localhost, 127.0.0.1, or ::1';
        }

        if ($database === '') {
            $reasons[] = 'MYSQL_TEST_DATABASE must be provided explicitly';
        } else {
            if (! str_ends_with($database, '_test')) {
                $reasons[] = 'MYSQL_TEST_DATABASE must end with _test';
            }

            if ($applicationDatabase !== '' && $database === $applicationDatabase) {
                $reasons[] = 'MYSQL_TEST_DATABASE must differ from the application database';
            }

            if (self::isProductionStyle($database)) {
                $reasons[] = 'MYSQL_TEST_DATABASE looks like a production database name';
            }
        }

        return $reasons;
    }

    public static function isProductionStyle(string $database): bool
    {
        $database = strtolower(trim($database));

        if (in_array($database, self::PRODUCTION_STYLE_DATABASES, true)) {
            return true;
        }

        return (bool) preg_match('/^u\d+_/', $database);
    }
}
