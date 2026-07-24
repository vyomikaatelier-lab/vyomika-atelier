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

    public function test_home_decor_is_canonical_shop_category_but_not_in_approved_shop_nav(): void
    {
        $canonicalSlugs = collect(ProductCatalog::canonicalCategories())
            ->where('section', 'shop')
            ->pluck('slug')
            ->all();

        $this->assertContains('home-decor', $canonicalSlugs);
        $this->assertContains('home-decor', StorefrontRoutes::shopCategorySlugs());

        $shopNavLabels = collect(config('site.nav', []))
            ->firstWhere('label', 'Shop')['children'] ?? [];

        $navSlugs = collect($shopNavLabels)
            ->map(fn (array $item) => $item['params']['slug'] ?? null)
            ->filter()
            ->values()
            ->all();

        $this->assertNotContains('home-decor', $navSlugs);
    }

    public function test_home_decor_has_no_seeded_catalog_products_yet(): void
    {
        $this->assertSame(
            0,
            Product::query()->whereHas('category', fn ($q) => $q->where('slug', 'home-decor'))->count()
        );
    }
}
