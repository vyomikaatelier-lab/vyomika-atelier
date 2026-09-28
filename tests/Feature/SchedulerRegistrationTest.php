<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerRegistrationTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setDailySummaryEnv(null);

        parent::tearDown();
    }

    public function test_daily_summary_is_absent_when_the_flag_is_unset(): void
    {
        $this->bootWithDailySummary(null);

        $this->assertFalse(config('leads.daily_summary_enabled'));
        $this->assertSame([], $this->eventsFor('leads:daily-summary'));
        $this->assertExpiryOnlySchedule();
    }

    public function test_daily_summary_is_absent_when_the_flag_is_false(): void
    {
        $this->bootWithDailySummary('false');

        $this->assertFalse(config('leads.daily_summary_enabled'));
        $this->assertSame([], $this->eventsFor('leads:daily-summary'));
        $this->assertExpiryOnlySchedule();
    }

    public function test_daily_summary_is_scheduled_at_0800_kolkata_when_enabled(): void
    {
        $this->bootWithDailySummary('true');

        $this->assertTrue(config('leads.daily_summary_enabled'));

        $summary = $this->eventsFor('leads:daily-summary');
        $this->assertCount(1, $summary);
        $this->assertSame('0 8 * * *', $summary[0]->expression);
        $this->assertSame('Asia/Kolkata', $summary[0]->timezone);

        $this->assertCount(1, $this->eventsFor('orders:expire-pending'));
        $this->assertSame('*/15 * * * *', $this->eventsFor('orders:expire-pending')[0]->expression);
        $this->assertSame([], $this->eventsFor('orders:reconcile-refunds'));
        $this->assertCount(2, app(Schedule::class)->events());
    }

    private function bootWithDailySummary(?string $value): void
    {
        $this->setDailySummaryEnv($value);
        $this->refreshApplication();
    }

    private function setDailySummaryEnv(?string $value): void
    {
        if ($value === null) {
            putenv('LEADS_DAILY_SUMMARY_ENABLED');
            unset($_ENV['LEADS_DAILY_SUMMARY_ENABLED'], $_SERVER['LEADS_DAILY_SUMMARY_ENABLED']);

            return;
        }

        putenv('LEADS_DAILY_SUMMARY_ENABLED='.$value);
        $_ENV['LEADS_DAILY_SUMMARY_ENABLED'] = $value;
        $_SERVER['LEADS_DAILY_SUMMARY_ENABLED'] = $value;
    }

    private function assertExpiryOnlySchedule(): void
    {
        $expire = $this->eventsFor('orders:expire-pending');
        $this->assertCount(1, $expire);
        $this->assertSame('*/15 * * * *', $expire[0]->expression);
        $this->assertSame([], $this->eventsFor('orders:reconcile-refunds'));
        $this->assertCount(1, app(Schedule::class)->events());
    }

    /**
     * @return list<object>
     */
    private function eventsFor(string $command): array
    {
        return array_values(array_filter(
            app(Schedule::class)->events(),
            fn ($event) => str_contains((string) $event->command, $command),
        ));
    }
}
