<?php

namespace Tests\Unit;

use Tests\TestCase;

class GithubTestsWorkflowTest extends TestCase
{
    public function test_tests_workflow_is_a_single_document_with_both_jobs(): void
    {
        $path = base_path('.github/workflows/tests.yml');
        $this->assertFileExists($path);

        $raw = file_get_contents($path);
        $this->assertNotFalse($raw);
        $this->assertSame(0, preg_match_all('/^---\s*$/m', $raw), 'Workflow must be a single YAML document.');
        $this->assertSame(1, preg_match_all('/^name:/m', $raw));
        $this->assertSame(1, preg_match_all('/^on:/m', $raw));
        $this->assertSame(1, preg_match_all('/^jobs:/m', $raw));

        $topLevel = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):/', $line, $matches) === 1) {
                $topLevel[] = $matches[1];
            }
        }

        $this->assertSame(['name', 'on', 'jobs'], $topLevel);
        $this->assertTrue(str_contains($raw, "name: Tests\n") || str_contains($raw, "name: Tests\r\n"));
        $this->assertTrue((bool) preg_match('/^  pull_request:\s*$/m', $raw));
        $this->assertTrue((bool) preg_match('/^  tests:\s*$/m', $raw));
        $this->assertTrue((bool) preg_match('/^  mysql-invitation-invariants:\s*$/m', $raw));
        $this->assertMatchesRegularExpression('/name: Run tests\s+run: php artisan test/', $raw);
        $this->assertTrue((bool) preg_match('/^\s+name: MySQL invitation uniqueness\s*$/m', $raw));
        $this->assertStringContainsString('tests/Feature/MysqlStaffInvitationUniquenessTest.php', $raw);
        $this->assertStringContainsString('tests/Feature/MysqlPaymentReconciliationMigrationTest.php', $raw);
        $this->assertStringContainsString('MYSQL_PAYMENT_RECONCILIATION_TEST', $raw);
        $this->assertStringContainsString('image: mysql:8.0', $raw);
        $this->assertStringContainsString('php artisan test', $raw);
        $this->assertSame(1, substr_count($raw, '- name: Run tests'));
    }
}
