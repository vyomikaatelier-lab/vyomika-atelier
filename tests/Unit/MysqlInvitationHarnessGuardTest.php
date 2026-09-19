<?php

namespace Tests\Unit;

use Tests\Support\MysqlInvitationHarnessGuard;
use Tests\TestCase;

class MysqlInvitationHarnessGuardTest extends TestCase
{
    public function test_opt_in_flag_must_be_explicit(): void
    {
        $this->assertFalse(MysqlInvitationHarnessGuard::isEnabled(null));
        $this->assertFalse(MysqlInvitationHarnessGuard::isEnabled(''));
        $this->assertFalse(MysqlInvitationHarnessGuard::isEnabled('true'));
        $this->assertTrue(MysqlInvitationHarnessGuard::isEnabled('1'));
    }

    public function test_safe_local_test_database_is_accepted(): void
    {
        $this->assertSame([], MysqlInvitationHarnessGuard::refusalReasons(
            '127.0.0.1',
            'vyomika_invitation_test',
            'vyomika_atelier',
        ));
        $this->assertSame([], MysqlInvitationHarnessGuard::refusalReasons(
            'localhost',
            'vyomika_invitation_test',
            ':memory:',
        ));
        $this->assertSame([], MysqlInvitationHarnessGuard::refusalReasons(
            '::1',
            'vyomika_invitation_test',
            'vyomika_atelier',
        ));
    }

    public function test_missing_database_is_refused(): void
    {
        $reasons = MysqlInvitationHarnessGuard::refusalReasons('127.0.0.1', null, 'vyomika_atelier');

        $this->assertNotEmpty($reasons);
        $this->assertTrue(collect($reasons)->contains(fn (string $reason) => str_contains($reason, 'provided explicitly')));
    }

    public function test_database_must_end_with_test_suffix(): void
    {
        $reasons = MysqlInvitationHarnessGuard::refusalReasons('127.0.0.1', 'vyomika_invitation', 'other');

        $this->assertNotEmpty($reasons);
        $this->assertTrue(collect($reasons)->contains(fn (string $reason) => str_contains($reason, '_test')));
    }

    public function test_application_database_name_is_refused(): void
    {
        $reasons = MysqlInvitationHarnessGuard::refusalReasons(
            '127.0.0.1',
            'vyomika_atelier_test',
            'vyomika_atelier_test',
        );

        $this->assertNotEmpty($reasons);
        $this->assertTrue(collect($reasons)->contains(fn (string $reason) => str_contains($reason, 'differ')));
    }

    public function test_production_style_names_are_refused(): void
    {
        foreach (['vyomika_atelier', 'production', 'prod', 'live', 'u123456_vyomika'] as $database) {
            $this->assertTrue(MysqlInvitationHarnessGuard::isProductionStyle($database), $database);
            $reasons = MysqlInvitationHarnessGuard::refusalReasons('127.0.0.1', $database, 'other');
            $this->assertNotEmpty($reasons, $database);
        }
    }

    public function test_remote_hosts_are_refused(): void
    {
        $reasons = MysqlInvitationHarnessGuard::refusalReasons(
            'db.example.com',
            'vyomika_invitation_test',
            'vyomika_atelier',
        );

        $this->assertNotEmpty($reasons);
        $this->assertTrue(collect($reasons)->contains(fn (string $reason) => str_contains($reason, 'localhost')));
    }
}
