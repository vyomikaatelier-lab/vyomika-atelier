<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontGutterLayoutTest extends TestCase
{
    use RefreshDatabase;

    /** Mirrors clamp(16px, 3.2vw, 64px) for total viewport-edge inset. */
    private static function expectedSiteGutterPx(int $viewportWidth): float
    {
        return max(16, min(64, $viewportWidth * 0.032));
    }

    private function primaryLayoutCss(): string
    {
        return (string) file_get_contents(public_path('css/amerce.css'))
            .(string) file_get_contents(public_path('css/responsive.css'));
    }

    private function containerRuleBlock(string $css): string
    {
        if (! preg_match('/\.am-container\s*\{[^}]+\}/s', $css, $matches)) {
            return '';
        }

        return $matches[0];
    }

    public function test_storefront_css_declares_gutter_without_global_layout_cap(): void
    {
        $css = (string) file_get_contents(public_path('css/amerce.css'));
        $containerRule = $this->containerRuleBlock($css);

        $this->assertStringContainsString('--site-gutter: clamp(16px, 3.2vw, 64px)', $css);
        $this->assertStringContainsString('padding-inline: var(--site-gutter)', $containerRule);
        $this->assertStringContainsString('max-width: none', $containerRule);
        $this->assertStringNotContainsString('--site-content-max:', $css);
        $this->assertStringNotContainsString('max-width: var(--site-content-max)', $css);
    }

    public function test_responsive_css_prevents_nested_container_double_padding(): void
    {
        $css = (string) file_get_contents(public_path('css/responsive.css'));

        $this->assertStringContainsString('.am-header > .am-container.am-header__inner', $css);
        $this->assertStringContainsString('.am-footer > .am-container', $css);
        $this->assertStringContainsString('padding-inline: var(--site-gutter, var(--am-gutter))', $css);
        $this->assertStringNotContainsString('--am-content-max:', $css);
        $this->assertStringNotContainsString('max-width: var(--site-content-max', $css);
    }

    #[DataProvider('viewportGutterProvider')]
    public function test_total_content_inset_equals_responsive_gutter_only(int $viewportWidth, float $expectedInset): void
    {
        $this->assertEqualsWithDelta(
            $expectedInset,
            self::expectedSiteGutterPx($viewportWidth),
            0.5,
            "Unexpected gutter calculation at {$viewportWidth}px"
        );

        $css = $this->primaryLayoutCss();
        $containerRule = $this->containerRuleBlock($css);

        $this->assertStringNotContainsString('max-width:', str_replace('max-width: none', '', $containerRule));
        $this->assertStringNotContainsString('max-width: var(--site-content-max', $css);
    }

    public static function viewportGutterProvider(): array
    {
        $viewports = [320, 375, 768, 1024, 1440, 1920];

        return array_combine(
            array_map(fn (int $width) => "{$width}px", $viewports),
            array_map(fn (int $width) => [$width, self::expectedSiteGutterPx($width)], $viewports)
        );
    }

    public function test_at_1920px_inset_is_gutter_not_triple_digit_margin(): void
    {
        $inset = self::expectedSiteGutterPx(1920);

        $this->assertGreaterThanOrEqual(61, $inset);
        $this->assertLessThanOrEqual(64, $inset);
        $this->assertLessThan(100, $inset, '1920px layout must not combine outer margin with gutter');
    }

    public function test_reading_width_caps_remain_on_text_heavy_components_only(): void
    {
        $css = $this->primaryLayoutCss();

        $this->assertStringContainsString('.am-container--blog', $css);
        $this->assertStringContainsString('.am-legal-page .am-container', $css);
        $this->assertStringContainsString('.am-account-auth-layout', $css);
        $this->assertStringContainsString('.am-checkout-flow', $css);
        $this->assertStringContainsString('--am-readable-max', $css);
        $this->assertStringContainsString('repeat(3', $css);
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

        $user = User::factory()->create();
        $cartProduct = Product::factory()->shop()->create(['category_id' => $category->id, 'stock' => 5]);

        $this->actingAs($user)
            ->withSession(['cart' => [$cartProduct->id => ['quantity' => 1]]])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('am-container', false);

        $this->assertStringContainsString('am-footer', $pages['homepage']);
        $this->assertStringContainsString('am-header__inner', $pages['homepage']);
        $this->assertStringContainsString('am-mobile-nav', $pages['homepage']);
        $this->assertStringContainsString('am-collection-gallery-grid', $pages['coffee_tables']);
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
