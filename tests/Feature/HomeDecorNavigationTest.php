<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ProductCatalog;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeDecorNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_decor_is_not_a_canonical_or_shop_nav_category(): void
    {
        $canonicalSlugs = collect(ProductCatalog::canonicalCategories())
            ->pluck('slug')
            ->all();

        $this->assertNotContains('home-decor', $canonicalSlugs);
        $this->assertNotContains('home-decor', StorefrontRoutes::shopCategorySlugs());
        $this->assertContains('home-decor', ProductCatalog::obsoleteCategorySlugs());

        $shopNavLabels = collect(config('site.nav', []))
            ->firstWhere('label', 'Shop')['children'] ?? [];

        $navSlugs = collect($shopNavLabels)
            ->map(fn (array $item) => $item['params']['slug'] ?? null)
            ->filter()
            ->values()
            ->all();

        $this->assertNotContains('home-decor', $navSlugs);
    }

    public function test_home_decor_has_no_seeded_catalog_products(): void
    {
        $this->assertSame(
            0,
            Product::query()->whereHas('category', fn ($q) => $q->where('slug', 'home-decor'))->count()
        );
    }
}
