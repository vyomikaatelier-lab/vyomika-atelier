<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Providers\AppServiceProvider;
use App\Support\CmsSettings;
use App\Support\CollectionContent;
use App\Support\PackageDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Composer runs `php artisan package:discover` during install/update. That
 * boots providers and registers routes before MySQL is guaranteed.
 * Database-backed CMS settings must stay deferred so discovery can use
 * config seed fallbacks safely.
 */
class PackageDiscoveryBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        PackageDiscovery::reset();

        parent::tearDown();
    }

    public function test_package_discovery_boot_does_not_touch_the_database(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'package:discover', '--ansi'];
        PackageDiscovery::reset();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        try {
            (new AppServiceProvider($this->app))->boot();
            CollectionContent::slugs();
        } finally {
            if ($originalArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }
            PackageDiscovery::reset();
        }

        $this->assertSame([], $queries);
        $this->assertNotEmpty(config('site.brand.name'), 'Config seed fallback must remain available.');
        $this->assertContains('mirror-frames', CollectionContent::slugs());
    }

    public function test_cms_settings_hydrate_skips_database_during_package_discovery(): void
    {
        SiteSetting::setValue('brand', [
            'name' => 'Must Not Hydrate During Discovery',
        ]);

        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'package:discover', '--ansi'];
        PackageDiscovery::reset();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        try {
            CmsSettings::clearCache();
            CmsSettings::hydrate();
        } finally {
            if ($originalArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }
            PackageDiscovery::reset();
        }

        $this->assertSame([], $queries);
        $this->assertNotSame('Must Not Hydrate During Discovery', config('site.brand.name'));

        $status = CmsSettings::hydrationStatus();
        $this->assertTrue($status['ran']);
        $this->assertFalse($status['table_found']);
        $this->assertNull($status['error']);
    }

    public function test_artisan_package_discover_succeeds_when_mysql_is_unavailable(): void
    {
        $process = Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing',
                'APP_KEY' => config('app.key'),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '1',
                'DB_DATABASE' => 'package_discover_should_not_connect',
                'DB_USERNAME' => 'root',
                'DB_PASSWORD' => 'wrong',
            ])
            ->run([PHP_BINARY, 'artisan', 'package:discover', '--ansi']);

        $this->assertTrue(
            $process->successful(),
            trim($process->errorOutput()."\n".$process->output())
        );
    }

    public function test_runtime_boot_still_hydrates_database_settings(): void
    {
        SiteSetting::setValue('brand', [
            'name' => 'Package Discovery Hydration Marker',
        ]);

        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'vyomika:deploy-check'];
        PackageDiscovery::reset();

        try {
            CmsSettings::clearCache();
            (new AppServiceProvider($this->app))->boot();
        } finally {
            if ($originalArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }
            PackageDiscovery::reset();
        }

        $this->assertSame('Package Discovery Hydration Marker', config('site.brand.name'));

        $status = CmsSettings::hydrationStatus();
        $this->assertTrue($status['ran']);
        $this->assertTrue($status['table_found']);
        $this->assertNull($status['error']);
        $this->assertContains('brand', $status['applied']);
    }
}
