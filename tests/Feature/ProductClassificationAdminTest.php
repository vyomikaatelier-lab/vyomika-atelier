<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductClassificationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_active_unclassified_products_after_seeder_rules(): void
    {
        $category = Category::factory()->create(['slug' => 'coffee-tables']);
        Product::factory()->create([
            'category_id' => $category->id,
            'slug' => 'aurora-glass-coffee-table',
            'section' => null,
            'purchase_mode' => null,
            'pricing_type' => null,
            'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\CorrectCatalogClassificationSeeder::class);

        $activeUnclassified = Product::query()->active()->unclassified()->count();
        $this->assertSame(0, $activeUnclassified);
    }
}
