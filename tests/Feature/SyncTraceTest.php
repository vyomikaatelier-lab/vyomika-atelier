<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A saved announcement reaching the homepage depends entirely on
 * AppServiceProvider::boot() hydrating config on every request. The rest of the
 * suite hydrates by hand before asserting, which passes even when that
 * boot-time hydration is broken — the failure mode that renders config seed
 * content on a live host while artisan diagnostics look correct.
 */
class SyncTraceTest extends TestCase
{
    use RefreshDatabase;

    private const TRACE_VALUE = 'SYNC-TRACE-8C03C3B-2026';

    private function saveAnnouncement(string $text): void
    {
        SiteSetting::setValue('homepage', [
            'announcement' => [
                'text' => $text,
                'link_label' => 'Trace Link',
                'link_href' => '/shop',
            ],
        ]);
    }

    /**
     * A web request boots the providers fresh, so reproduce that instead of
     * hydrating by hand.
     */
    private function bootAsFreshRequestWould(): void
    {
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_boot_time_hydration_puts_a_saved_announcement_into_the_homepage_html(): void
    {
        $this->saveAnnouncement(self::TRACE_VALUE);

        $this->bootAsFreshRequestWould();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(self::TRACE_VALUE, false);
    }

    public function test_boot_time_hydration_does_not_leave_the_config_seed_in_the_homepage_html(): void
    {
        $seed = config('site.announcement.text');

        $this->saveAnnouncement(self::TRACE_VALUE);
        $this->bootAsFreshRequestWould();

        $response = $this->get(route('home'))->assertOk();

        $this->assertIsString($seed);
        $response->assertDontSee($seed, false);
    }

    public function test_sync_trace_reports_no_divergence_when_the_saved_value_reaches_the_html(): void
    {
        $this->saveAnnouncement(self::TRACE_VALUE);
        $this->bootAsFreshRequestWould();

        $this->artisan('vyomika:sync-trace', ['--expect' => self::TRACE_VALUE])
            ->expectsOutputToContain('NO DIVERGENCE INSIDE PHP.')
            ->expectsOutputToContain('PASS: rendered homepage HTML contains '.self::TRACE_VALUE)
            ->assertExitCode(0);
    }

    public function test_sync_trace_blames_boot_time_hydration_when_config_is_behind_the_database(): void
    {
        // Saved after the application booted and deliberately not hydrated: this
        // is the live symptom, where the database is ahead of boot-time config.
        $this->saveAnnouncement(self::TRACE_VALUE);

        $this->artisan('vyomika:sync-trace')
            ->expectsOutputToContain('DIVERGENCE AT BOOT-TIME HYDRATION.')
            ->assertExitCode(0);
    }

    public function test_sync_trace_fails_the_expectation_when_the_value_never_reaches_the_html(): void
    {
        $this->saveAnnouncement(self::TRACE_VALUE);

        $this->artisan('vyomika:sync-trace', ['--expect' => self::TRACE_VALUE])
            ->expectsOutputToContain('FAIL: rendered homepage HTML does not contain '.self::TRACE_VALUE)
            ->assertExitCode(1);
    }

    public function test_sync_trace_reports_the_database_value_and_the_config_seed_separately(): void
    {
        $this->saveAnnouncement(self::TRACE_VALUE);
        $this->bootAsFreshRequestWould();

        $this->artisan('vyomika:sync-trace')
            ->expectsOutputToContain('homepage.announcement.text: '.self::TRACE_VALUE)
            ->expectsOutputToContain('site_settings table: present')
            ->assertExitCode(0);
    }

    public function test_hydration_status_records_which_keys_were_applied(): void
    {
        $this->saveAnnouncement(self::TRACE_VALUE);
        $this->bootAsFreshRequestWould();

        $status = \App\Support\CmsSettings::hydrationStatus();

        $this->assertTrue($status['ran']);
        $this->assertTrue($status['table_found']);
        $this->assertNull($status['error']);
        $this->assertContains('homepage.announcement', $status['applied']);
    }
}
