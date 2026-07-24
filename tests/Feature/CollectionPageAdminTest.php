<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollectionPageAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_responsive_collection_hero_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $desktop = UploadedFile::fake()->image('coffee-desktop.jpg', 1600, 900);
        $tablet = UploadedFile::fake()->image('coffee-tablet.jpg', 1200, 800);

        $this->actingAsAdmin($admin)->put(route('admin.collection-pages.update', 'coffee-tables'), [
            'hero_title' => 'Coffee Tables Hero',
            'hero_image_file' => $desktop,
            'hero_image_tablet_file' => $tablet,
        ])->assertRedirect(route('admin.collection-pages.edit', ['slug' => 'coffee-tables', 'saved' => 1]));

        $stored = data_get(SiteSetting::getValue('collection_pages', []), 'coffee-tables.hero');
        $this->assertSame('Coffee Tables Hero', $stored['title'] ?? null);
        $this->assertNotEmpty($stored['image'] ?? null);
        $this->assertNotEmpty($stored['image_tablet'] ?? null);
        Storage::disk('public')->assertExists($stored['image']);
        Storage::disk('public')->assertExists($stored['image_tablet']);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Coffee Tables Hero', false)
            ->assertSee('--hero-bg-desktop:', false)
            ->assertSee('--hero-bg-tablet:', false);
    }
}
