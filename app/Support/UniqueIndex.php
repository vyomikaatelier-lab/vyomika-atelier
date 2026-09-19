<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Matches a named unique index / driver duplicate-key condition.
 *
 * Unknown-column errors and arbitrary SQLSTATE 23000 failures are not treated
 * as a duplicate of the guarded value.
 */
final class UniqueIndex
{
    public static function isDuplicate(Throwable $exception, string $indexName, string $column): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'unknown column') || str_contains($message, 'no such column')) {
            return false;
        }

        $named = str_contains($message, strtolower($indexName));
        $sqliteColumn = str_contains($message, 'unique constraint failed')
            && str_contains($message, strtolower($column));

        if (! $named && ! $sqliteColumn) {
            return false;
        }

        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23000' && in_array($driverCode, ['1062', '19'], true);
    }
}
