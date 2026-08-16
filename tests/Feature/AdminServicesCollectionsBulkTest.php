<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\CollectionContent;
use App\Support\ShopCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServicesCollectionsBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_hide_services_from_nav(): void
    {
        $admin = User::factory()->admin()->create();

        $first = Service::query()->create([
            'name' => 'Partitions Bulk',
            'slug' => 'partitions',
            'lead_form' => 'popup',
            'is_active' => true,
            'hide_from_nav' => false,
        ]);

        $second = Service::query()->create([
            'name' => 'Racks Bulk',
            'slug' => 'rack-systems-metal-pvd',
            'lead_form' => 'popup',
            'is_active' => true,
            'hide_from_nav' => false,
        ]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.services.bulk'), [
                'action' => 'hide_from_nav',
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('admin.services.index'))
            ->assertSessionHas('success');

        $this->assertTrue($first->fresh()->hide_from_nav);
        $this->assertTrue($second->fresh()->hide_from_nav);
    }

    public function test_studio_nav_hides_inactive_or_nav_hidden_services(): void
    {
        Service::query()->create([
            'name' => 'Partitions',
            'slug' => 'partitions',
            'lead_form' => 'popup',
            'is_active' => true,
            'hide_from_nav' => true,
        ]);

        Service::query()->create([
            'name' => 'Racks',
            'slug' => 'rack-systems-metal-pvd',
            'lead_form' => 'popup',
            'is_active' => false,
        ]);

        $nav = ShopCatalog::filterNav(config('site.nav', []));

        $studio = collect($nav)->firstWhere('label', 'Studio');
        $this->assertIsArray($studio);

        $labels = collect($studio['children'] ?? [])->pluck('label')->all();
        $this->assertNotContains('PVD Partitions', $labels);
        $this->assertNotContains('Metal PVD Rack Systems', $labels);
    }

    public function test_admin_can_create_and_delete_custom_collection_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->post(route('admin.collection-pages.store'), [
                'name' => 'Side Tables',
                'slug' => 'side-tables',
            ])
            ->assertRedirect(route('admin.collection-pages.edit', 'side-tables'))
            ->assertSessionHas('success');

        $this->assertContains('side-tables', CollectionContent::slugs());
        $this->assertDatabaseHas('categories', [
            'slug' => 'side-tables',
            'section' => Product::SECTION_SHOP,
            'is_active' => true,
        ]);

        $nav = SiteSetting::getValue('nav', config('site.nav', []));
        $shop = collect($nav)->firstWhere('label', 'Shop');
        $this->assertTrue(
            collect($shop['children'] ?? [])->contains(fn (array $link) => ($link['params']['slug'] ?? null) === 'side-tables')
        );

        $this->actingAsAdmin($admin)
            ->delete(route('admin.collection-pages.destroy', 'side-tables'))
            ->assertRedirect(route('admin.collection-pages.index'))
            ->assertSessionHas('success');

        $this->assertNotContains('side-tables', CollectionContent::slugs());
        $this->assertNull(Category::query()->where('slug', 'side-tables')->first());
    }

    public function test_admin_can_bulk_deactivate_collection_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => Product::SECTION_SHOP, 'is_active' => true]
        );
        $category->update(['is_active' => true]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.collection-pages.bulk'), [
                'action' => 'deactivate',
                'slugs' => ['coffee-tables'],
            ])
            ->assertRedirect(route('admin.collection-pages.index'))
            ->assertSessionHas('success');

        $this->assertFalse(Category::query()->where('slug', 'coffee-tables')->value('is_active'));
    }

    public function test_services_index_shows_bulk_controls(): void
    {
        $admin = User::factory()->admin()->create();

        Service::query()->create([
            'name' => 'Door Systems',
            'slug' => 'slim-profile-door-system',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('service-select-all', false)
            ->assertSee('Hide from main menu', false)
            ->assertSee('service-bulk-check', false);
    }

    public function test_collection_pages_index_shows_bulk_and_add_controls(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->get(route('admin.collection-pages.index'))
            ->assertOk()
            ->assertSee('Add Collection Page', false)
            ->assertSee('collection-select-all', false)
            ->assertSee('collection-bulk-check', false);
    }
}
