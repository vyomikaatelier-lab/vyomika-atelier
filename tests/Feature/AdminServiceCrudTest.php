<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceDesign;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminServiceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_service_with_design(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAsAdmin($admin)->post(route('admin.services.store'), [
            'name' => 'Test Partitions',
            'slug' => 'test-partitions',
            'summary' => 'Custom partitions',
            'lead_form' => 'popup',
            'rate_per_sqft' => 1800,
            'is_active' => '1',
            'has_calculator' => '1',
            'has_designs' => '1',
            'designs' => [
                [
                    'name' => 'Wave Partition',
                    'slug' => 'wave-partition',
                    'description' => 'Wave pattern divider',
                    'is_active' => '1',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.services.index'));
        $response->assertSessionHas('success');

        $service = Service::query()->where('slug', 'test-partitions')->first();
        $this->assertNotNull($service);
        $this->assertSame('Test Partitions', $service->name);

        $design = ServiceDesign::query()->where('service_id', $service->id)->first();
        $this->assertNotNull($design);
        $this->assertSame('wave-partition', $design->slug);
    }

    public function test_admin_can_update_and_delete_service(): void
    {
        $admin = User::factory()->admin()->create();

        $service = Service::query()->create([
            'name' => 'Old Service',
            'slug' => 'old-service',
            'lead_form' => 'popup',
            'rate_per_sqft' => 1500,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.services.update', $service), [
            'name' => 'Updated Service',
            'slug' => 'updated-service',
            'lead_form' => 'inline',
            'rate_per_sqft' => 1600,
            'is_active' => '1',
        ])->assertRedirect(route('admin.services.index'));

        $service->refresh();
        $this->assertSame('Updated Service', $service->name);
        $this->assertSame('updated-service', $service->slug);

        $this->actingAsAdmin($admin)->delete(route('admin.services.destroy', $service))
            ->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_admin_can_upload_responsive_service_cover_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $service = Service::query()->create([
            'name' => 'Partitions',
            'slug' => 'partitions',
            'lead_form' => 'popup',
            'rate_per_sqft' => 1800,
            'is_active' => true,
        ]);

        $desktop = UploadedFile::fake()->image('service-desktop.jpg', 1600, 900);
        $mobile = UploadedFile::fake()->image('service-mobile.jpg', 800, 1200);

        $this->actingAsAdmin($admin)->put(route('admin.services.update', $service), [
            'name' => 'Partitions',
            'slug' => 'partitions',
            'lead_form' => 'popup',
            'rate_per_sqft' => 1800,
            'is_active' => '1',
            'hero_image_file' => $desktop,
            'hero_image_mobile_file' => $mobile,
        ])->assertRedirect(route('admin.services.index'));

        $stored = data_get(SiteSetting::getValue('service_page_heroes', []), 'partitions');
        $this->assertNotEmpty($stored['image'] ?? null);
        $this->assertNotEmpty($stored['image_mobile'] ?? null);
        Storage::disk('public')->assertExists($stored['image']);
        Storage::disk('public')->assertExists($stored['image_mobile']);

        $service->refresh();
        $this->assertSame($stored['image'], $service->image);
    }
}
