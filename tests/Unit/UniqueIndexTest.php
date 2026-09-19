<?php

namespace Tests\Unit;

use App\Support\UniqueIndex;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class UniqueIndexTest extends TestCase
{
    public function test_named_mysql_duplicate_key_is_a_pending_email_conflict(): void
    {
        $exception = $this->queryException(
            UniqueConstraintViolationException::class,
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'staff@example.com\' for key \'staff_inv_pending_email_uq\'',
            ['23000', 1062, 'Duplicate entry for key staff_inv_pending_email_uq'],
        );

        $this->assertTrue(UniqueIndex::isDuplicate($exception, 'staff_inv_pending_email_uq', 'pending_email'));
    }

    public function test_sqlite_unique_constraint_failed_on_pending_email_is_a_conflict(): void
    {
        $exception = $this->queryException(
            UniqueConstraintViolationException::class,
            'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: staff_invitations.pending_email',
            ['23000', 19, 'UNIQUE constraint failed: staff_invitations.pending_email'],
        );

        $this->assertTrue(UniqueIndex::isDuplicate($exception, 'staff_inv_pending_email_uq', 'pending_email'));
    }

    public function test_unknown_column_pending_email_is_not_a_duplicate(): void
    {
        $exception = $this->queryException(
            QueryException::class,
            'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'pending_email\' in \'field list\'',
            ['42S22', 1054, 'Unknown column pending_email'],
        );

        $this->assertFalse(UniqueIndex::isDuplicate($exception, 'staff_inv_pending_email_uq', 'pending_email'));
    }

    public function test_arbitrary_sqlstate_23000_is_not_a_duplicate(): void
    {
        $exception = $this->queryException(
            QueryException::class,
            'SQLSTATE[23000]: Integrity constraint violation: 1048 Column \'name\' cannot be null',
            ['23000', 1048, 'Column name cannot be null'],
        );

        $this->assertFalse(UniqueIndex::isDuplicate($exception, 'staff_inv_pending_email_uq', 'pending_email'));
    }

    public function test_unrelated_exceptions_are_not_duplicates(): void
    {
        $this->assertFalse(UniqueIndex::isDuplicate(
            new RuntimeException('pending_email failed'),
            'staff_inv_pending_email_uq',
            'pending_email',
        ));
    }

    /**
     * @param  class-string<QueryException>  $class
     * @param  array{0: string, 1: int, 2: string}  $errorInfo
     */
    private function queryException(string $class, string $message, array $errorInfo): QueryException
    {
        $previous = new PDOException($message);
        $previous->errorInfo = $errorInfo;

        return new $class('mysql', 'insert into staff_invitations (pending_email) values (?)', ['staff@example.com'], $previous);
    }
}
