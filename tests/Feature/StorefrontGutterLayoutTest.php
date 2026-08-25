<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontGutterLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_css_declares_shared_gutter_tokens(): void
    {
        $css = (string) file_get_contents(public_path('css/amerce.css'));

        $this->assertStringContainsString('--site-gutter: clamp(16px, 3.2vw, 64px)', $css);
        $this->assertStringContainsString('--site-content-max: 1440px', $css);
        $this->assertStringContainsString('max-width: var(--site-content-max)', $css);
        $this->assertStringContainsString('padding-inline: var(--site-gutter)', $css);
    }

    public function test_responsive_css_prevents_nested_container_double_padding(): void
    {
        $css = (string) file_get_contents(public_path('css/responsive.css'));

        $this->assertStringContainsString('.am-container .am-container', $css);
        $this->assertStringNotContainsString('--am-gutter: clamp(12px, 2vw, 32px)', $css);
    }

    public function test_representative_storefront_pages_use_shared_container(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => Product::SECTION_SHOP, 'is_active' => true]
        );

        Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Gutter Test Table',
            'is_gallery_visible' => true,
        ]);

        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Gutter Test PDP',
            'stock' => 5,
        ]);

        Service::query()->create([
            'name' => 'PVD Partitions',
            'slug' => 'partitions',
            'summary' => 'Studio partitions.',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'PVD Partitions', 'section' => Product::SECTION_STUDIO, 'is_active' => true]
        );

        $pages = [
            'homepage' => $this->get(route('home'))->assertOk()->getContent(),
            'mirror_frames' => $this->get(route('shop.mirror-frames.index'))->assertOk()->getContent(),
            'coffee_tables' => $this->get(route('shop.show', 'coffee-tables'))->assertOk()->getContent(),
            'search' => $this->get(route('search', ['q' => 'table']))->assertOk()->getContent(),
            'pdp' => $this->get(route('shop.show', $product->slug))->assertOk()->getContent(),
            'cart' => $this->get(route('cart.index'))->assertOk()->getContent(),
            'login' => $this->get(route('account.login'))->assertOk()->getContent(),
            'register' => $this->get(route('account.register'))->assertOk()->getContent(),
            'studio_partitions' => $this->get(route('studio.show', 'pvd-partitions'))->assertOk()->getContent(),
            'projects' => $this->get(route('projects.index'))->assertOk()->getContent(),
        ];

        foreach ($pages as $label => $html) {
            $this->assertStringContainsString('am-container', $html, "Expected shared container on {$label}");
        }

        $responsiveCss = (string) file_get_contents(public_path('css/responsive.css'));
        $this->assertStringContainsString('overflow-x: clip', $responsiveCss);

        $this->get(route('checkout.index'))
            ->assertRedirect();

        $user = User::factory()->create();
        $categoryId = $category->id;
        $cartProduct = Product::factory()->shop()->create(['category_id' => $categoryId, 'stock' => 5]);

        $checkoutHtml = $this->actingAs($user)
            ->withSession(['cart' => [$cartProduct->id => ['quantity' => 1]]])
            ->get(route('checkout.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('am-container', $checkoutHtml);

        $footerHtml = $pages['homepage'];
        $this->assertStringContainsString('am-footer', $footerHtml);
        $this->assertStringContainsString('am-header__inner', $footerHtml);
        $this->assertStringContainsString('am-mobile-nav', $footerHtml);
    }

    public function test_gallery_cards_keep_title_and_price_visible_without_fake_ratings(): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'coffee-tables'],
            ['name' => 'Coffee Tables', 'section' => Product::SECTION_SHOP, 'is_active' => true]
        );

        Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Aligned Gallery Table',
            'price' => 14000,
            'is_gallery_visible' => true,
        ]);

        $html = $this->get(route('shop.show', 'coffee-tables'))->assertOk()->getContent();

        $this->assertStringContainsString('Aligned Gallery Table', $html);
        $this->assertStringContainsString('₹14,000', $html);
        $this->assertStringNotContainsString('★★★★★', $html);
        $this->assertStringNotContainsString('am-product-card__rating', $html);
    }

    public function test_blog_tree_remains_unchanged_vs_approved_baseline(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $output = shell_exec('git -C '.escapeshellarg($repoRoot).' diff b01aa8a -- resources/views/blog 2>&1');

        $this->assertSame('', trim((string) $output));
    }
}
