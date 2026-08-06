<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Providers\AppServiceProvider;
use App\Support\CmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Composer runs `php artisan package:discover` during install/update. That
 * boots providers before MySQL is guaranteed. Database-backed CMS settings
 * must stay deferred so discovery can use config seed fallbacks safely.
 */
class PackageDiscoveryBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_discovery_boot_does_not_touch_the_database(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'package:discover', '--ansi'];

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        try {
            (new AppServiceProvider($this->app))->boot();
        } finally {
            if ($originalArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }
        }

        $this->assertSame([], $queries);
        $this->assertNotEmpty(config('site.brand.name'), 'Config seed fallback must remain available.');
    }

    public function test_runtime_boot_still_hydrates_database_settings(): void
    {
        SiteSetting::setValue('brand', [
            'name' => 'Package Discovery Hydration Marker',
        ]);

        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'vyomika:deploy-check'];

        try {
            CmsSettings::clearCache();
            (new AppServiceProvider($this->app))->boot();
        } finally {
            if ($originalArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }
        }

        $this->assertSame('Package Discovery Hydration Marker', config('site.brand.name'));

        $status = CmsSettings::hydrationStatus();
        $this->assertTrue($status['ran']);
        $this->assertTrue($status['table_found']);
        $this->assertNull($status['error']);
        $this->assertContains('brand', $status['applied']);
    }
}
