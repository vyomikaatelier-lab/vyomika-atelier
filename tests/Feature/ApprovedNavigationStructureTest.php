<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\ProductCatalog;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovedNavigationStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_nav_slugs_match_approved_list(): void
    {
        $this->assertSame([
            'mirror-frames',
            'corner-tables',
            'coffee-tables',
            'glass-tables',
            'door-handles',
            'bespoke-metal-furniture',
        ], StorefrontRoutes::shopCategorySlugs());

        $shopCanonical = collect(ProductCatalog::canonicalCategories())
            ->where('section', 'shop')
            ->pluck('slug')
            ->values()
            ->all();

        $this->assertSame(StorefrontRoutes::shopCategorySlugs(), $shopCanonical);
    }

    public function test_studio_services_and_category_slugs_align(): void
    {
        $this->assertSame([
            'partitions',
            'slim-profile-door-system',
            'main-entrance-pvd-doors',
            'rack-systems-metal-pvd',
        ], ProductCatalog::studioCategorySlugs());

        $this->assertSame([], ProductCatalog::railingsCategorySlugs());

        foreach (ProductCatalog::studioCategorySlugs() as $serviceSlug) {
            $this->assertNotNull(StorefrontRoutes::studioUrlForService($serviceSlug));
        }
    }

    public function test_sync_does_not_reactivate_obsolete_categories(): void
    {
        ProductCatalog::syncCanonicalCategories();

        Category::query()->where('slug', 'home-decor')->update(['is_active' => true]);
        Category::query()->where('slug', 'railings')->update(['is_active' => true]);

        ProductCatalog::syncCanonicalCategories();

        foreach (ProductCatalog::obsoleteCategorySlugs() as $slug) {
            $category = Category::query()->where('slug', $slug)->first();
            $this->assertNotNull($category, $slug);
            $this->assertFalse($category->fresh()->is_active, $slug.' should stay inactive');
        }

        foreach (ProductCatalog::canonicalCategories() as $cat) {
            $this->assertTrue(
                Category::query()->where('slug', $cat['slug'])->where('is_active', true)->exists(),
                $cat['slug'].' should be active'
            );
        }
    }
}
