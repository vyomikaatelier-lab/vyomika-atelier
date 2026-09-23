<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Matches a named unique index / driver duplicate-key condition.
 *
 * SQLite matches the failed table.column target, not a substring of the
 * Laravel-appended SQL. Unknown-column errors and arbitrary SQLSTATE 23000
 * failures are not treated as a duplicate of the guarded value.
 */
final class UniqueIndex
{
    public static function isDuplicate(Throwable $exception, string $indexName, string $table, string $column): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $message = strtolower($exception->getMessage());
        $driverMessage = self::driverMessage($message);

        if (str_contains($driverMessage, 'unknown column') || str_contains($driverMessage, 'no such column')) {
            return false;
        }

        $named = str_contains($driverMessage, strtolower($indexName));
        $sqliteTarget = self::sqliteFailedOn($driverMessage, $table, $column);

        if (! $named && ! $sqliteTarget) {
            return false;
        }

        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23000' && in_array($driverCode, ['1062', '19'], true);
    }

    private static function driverMessage(string $message): string
    {
        $separator = strpos($message, ' (connection:');

        if ($separator === false) {
            return $message;
        }

        return substr($message, 0, $separator);
    }

    private static function sqliteFailedOn(string $driverMessage, string $table, string $column): bool
    {
        if (! preg_match('/unique constraint failed:\s*(.+)$/i', $driverMessage, $matches)) {
            return false;
        }

        $targets = array_map(
            static fn (string $part): string => strtolower(trim($part)),
            explode(',', $matches[1]),
        );

        return in_array(strtolower($table.'.'.$column), $targets, true);
    }
}
