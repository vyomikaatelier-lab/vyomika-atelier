<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * SQLite can return SQLITE_BUSY while a serialized writer holds the file.
 * MySQL row locks wait instead. Retry only that local busy condition.
 */
final class SqliteBusy
{
    public static function retry(callable $callback): mixed
    {
        return retry(8, $callback, 25, fn (Throwable $exception) => self::matches($exception));
    }

    public static function matches(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'database is locked')
            || str_contains($message, 'database table is locked');
    }
}
