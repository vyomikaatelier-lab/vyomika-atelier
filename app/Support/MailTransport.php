<?php

namespace App\Support;

/**
 * SMTP transport bounds for invitation delivery.
 *
 * Laravel 11 / Symfony Mailer 7.4 honour mail.mailers.smtp.timeout via
 * EsmtpTransport SocketStream::setTimeout(). A null value is skipped by
 * Laravel and falls back to PHP default_socket_timeout, which can exceed
 * the web request limit and surface as an uncaught 500.
 *
 * Production: set MAIL_SMTP_TIMEOUT=8 (seconds). Keep it below PHP
 * max_execution_time (commonly 30). Do not set credentials here.
 */
final class MailTransport
{
    public const DEFAULT_SMTP_TIMEOUT_SECONDS = 8;

    public const MAX_SMTP_TIMEOUT_SECONDS = 20;

    public static function smtpTimeoutSeconds(mixed $raw = 8): int
    {
        if (! is_numeric($raw)) {
            return self::DEFAULT_SMTP_TIMEOUT_SECONDS;
        }

        $timeout = (int) $raw;

        if ($timeout < 1) {
            return self::DEFAULT_SMTP_TIMEOUT_SECONDS;
        }

        return min($timeout, self::MAX_SMTP_TIMEOUT_SECONDS);
    }
}
