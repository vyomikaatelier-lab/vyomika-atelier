<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\LegalContent;
use App\Support\Seo\PageSeo;
use App\Support\StorefrontNavigation;
use App\Support\StorefrontRoutes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeNavFooterLegalSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_shop_category_disappears_from_nav_homepage_and_sitemap_and_reactivation_restores_it(): void
    {
        $category = $this->publishedShopCategory('nav-test-tables', 'Nav Test Tables');
        $url = route('shop.show', 'nav-test-tables');

        $this->assertUrlOnNavigationHomepageAndSitemap($url);

        $category->update(['is_active' => false]);

        $this->assertUrlAbsentFromNavigationHomepageAndSitemap($url);

        $category->update(['is_active' => true]);

        $this->assertUrlOnNavigationHomepageAndSitemap($url);
    }

    public function test_inactive_studio_service_disappears_from_all_navigation_locations(): void
    {
        $service = $this->publishedStudioService(
            'rack-systems-metal-pvd',
            'metal-pvd-rack-systems',
            'rack-systems-metal-pvd'
        );
        $url = route('studio.show', 'metal-pvd-rack-systems');

        $html = $this->get(route('home'))->assertOk()->getContent();
        foreach ($this->navigationSurfaces($html) as $label => $surface) {
            $this->assertStringContainsString($url, $surface, "Expected {$url} in {$label}");
        }
        $this->assertStringContainsString($url, $this->get(route('sitemap'))->assertOk()->getContent());

        $service->update(['is_active' => false]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        foreach ($this->navigationSurfaces($html) as $label => $surface) {
            $this->assertStringNotContainsString($url, $surface, "Did not expect {$url} in {$label}");
        }
        $this->assertStringNotContainsString($url, $this->get(route('sitemap'))->assertOk()->getContent());
    }

    public function test_active_but_empty_category_remains_hidden(): void
    {
        Category::query()->create([
            'name' => 'Empty Nav Tables',
            'slug' => 'empty-nav-tables',
            'section' => Product::SECTION_SHOP,
            'is_active' => true,
            'sort_order' => 50,
        ]);

        $url = route('shop.show', 'empty-nav-tables');

        $this->assertUrlAbsentFromNavigationHomepageAndSitemap($url);
    }

    public function test_homepage_has_no_generic_shop_cta_or_all_products_copy(): void
    {
        $this->publishedShopCategory('mirror-frames', 'Mirror Frames');

        config([
            'site.announcement' => [
                'text' => 'Resolve generic shop CTA',
                'link_label' => 'View All Products',
                'link_href' => '/shop',
            ],
            'site.hero.slides' => [[
                'kicker' => 'Test',
                'title' => 'Generic hero CTA',
                'description' => 'Resolves /shop at render time.',
                'image' => '/images/test.jpg',
                'cta_label' => 'All Products',
                'cta_href' => '/shop',
            ]],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertNoGenericShopHref($html);
        $this->assertStringNotContainsString('All Products', $html);
        $this->assertStringNotContainsString('View All Products', $html);
        $this->assertSame(
            StorefrontNavigation::primaryPublishedShopUrl(),
            StorefrontNavigation::resolveHref('/shop')
        );
        $this->assertStringContainsString(StorefrontNavigation::primaryPublishedShopUrl(), $html);
    }

    public function test_hidden_and_inactive_products_never_appear_on_homepage(): void
    {
        $category = $this->publishedShopCategory('nav-test-tables', 'Nav Test Tables');

        Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Visible Home Table',
            'is_active' => true,
            'is_gallery_visible' => true,
            'is_featured' => true,
        ]);
        Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Secret Inactive Home Table',
            'is_active' => false,
            'is_gallery_visible' => true,
            'is_featured' => true,
        ]);
        Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => 'Gallery Hidden Home Table',
            'is_active' => true,
            'is_gallery_visible' => false,
            'is_featured' => true,
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Unclassified Home Table',
            'section' => null,
            'purchase_mode' => null,
            'pricing_type' => null,
            'is_active' => true,
            'is_gallery_visible' => true,
            'is_featured' => true,
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Visible Home Table', $html);
        $this->assertStringNotContainsString('Secret Inactive Home Table', $html);
        $this->assertStringNotContainsString('Gallery Hidden Home Table', $html);
        $this->assertStringNotContainsString('Unclassified Home Table', $html);
    }

    public function test_legal_pages_emit_unique_seo_tags_without_product_schema(): void
    {
        $siteDescription = PageSeo::siteDefaults()['description'] ?? '';

        foreach (LegalContent::pageRoutes() as $key => $routeName) {
            $page = LegalContent::page($key);
            $url = route($routeName);
            $html = $this->get($url)->assertOk()->getContent();
            $seo = LegalContent::pageSeo($key);

            $this->assertSame([$seo['description']], $this->metaContents($html, 'description'), $url);
            $this->assertSame([$seo['robots']], $this->metaContents($html, 'robots'), $url);
            $this->assertSame(['index,follow'], $this->metaContents($html, 'robots'), $url);
            $this->assertCount(1, $this->canonicalHrefs($html), $url);
            $this->assertSame([$url], $this->canonicalHrefs($html), $url);
            $this->assertStringContainsString('<title>'.e($seo['title']).'</title>', $html);
            $this->assertSame([$seo['og_title']], $this->ogContents($html, 'og:title'), $url);
            $this->assertSame([$seo['og_description']], $this->ogContents($html, 'og:description'), $url);
            $this->assertNotSame($siteDescription, $seo['og_description'], $url);
            $this->assertSame($page['meta_title'] ?? null, $seo['og_title'], $url);
            $this->assertSame($page['meta_description'] ?? null, $seo['og_description'], $url);
            $this->assertStringContainsString('"@type":"Organization"', str_replace(' ', '', $html));
            $this->assertStringNotContainsString('"@type":"Product"', str_replace(' ', '', $html));
            $this->assertStringNotContainsString('"@type":"Offer"', str_replace(' ', '', $html));
            $this->assertStringNotContainsString('"@type":"LocalBusiness"', str_replace(' ', '', $html));
        }

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();
        foreach (LegalContent::pageRoutes() as $routeName) {
            $this->assertStringContainsString(route($routeName), $xml);
        }
    }

    public function test_404_emits_only_noindex_nofollow_robots(): void
    {
        $html = $this->get('/this-page-does-not-exist-'.uniqid())->assertNotFound()->getContent();

        $this->assertSame(['noindex,nofollow'], $this->metaContents($html, 'robots'));
        $this->assertStringNotContainsString('name="robots" content="index,follow"', $html);
        $this->assertStringNotContainsString('name="robots" content="noindex, nofollow"', $html);
    }

    public function test_shop_remains_301_and_absent_from_sitemap(): void
    {
        $this->publishedShopCategory('mirror-frames', 'Mirror Frames');

        $this->get(route('shop.index'))
            ->assertStatus(301)
            ->assertRedirect(StorefrontNavigation::primaryPublishedShopUrl());

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<loc>'.route('shop.index').'</loc>', $xml);
    }

    public function test_cart_checkout_account_and_search_remain_noindex(): void
    {
        $this->get(route('cart.index'))->assertOk()->assertSee('noindex', false);
        $this->get(route('search', ['q' => 'table']))->assertOk()->assertSee('noindex', false);
        $this->get(route('account.login'))->assertOk()->assertSee('noindex', false);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account'))->assertOk()->assertSee('noindex', false);

        $category = $this->publishedShopCategory('nav-test-tables', 'Nav Test Tables');
        $product = Product::factory()->shop()->create([
            'category_id' => $category->id,
            'stock' => 5,
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['cart' => [$product->id => ['quantity' => 1]]])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_header_and_footer_share_site_gutter_guides(): void
    {
        $css = (string) file_get_contents(public_path('css/responsive.css'));

        $this->assertStringContainsString('.am-header > .am-container.am-header__inner', $css);
        $this->assertStringContainsString('.am-footer > .am-container', $css);
        $this->assertStringContainsString('padding-inline: var(--site-gutter, var(--am-gutter))', $css);
        $this->assertStringNotContainsString('max-width: 1440px', $css);
        $this->assertStringNotContainsString('--site-content-max:', (string) file_get_contents(public_path('css/amerce.css')));
    }

    public function test_blog_tree_remains_identical_to_approved_checkpoint(): void
    {
        $approved = trim((string) shell_exec('git diff b01aa8a -- resources/views/blog 2>&1'));

        $this->assertSame('', $approved, 'Blog tree must remain identical to approved checkpoint b01aa8a.');
    }

    private function publishedShopCategory(string $slug, string $name): Category
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'section' => Product::SECTION_SHOP,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
        $category->update([
            'name' => $name,
            'section' => Product::SECTION_SHOP,
            'is_active' => true,
        ]);

        Product::factory()->shop()->create([
            'category_id' => $category->id,
            'name' => $name.' Public Product',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        return $category->fresh();
    }

    private function publishedStudioService(string $serviceSlug, string $urlSlug, string $categorySlug): Service
    {
        $service = Service::query()->create([
            'name' => StorefrontRoutes::studioServiceLabel($serviceSlug),
            'slug' => $serviceSlug,
            'summary' => 'Eligible studio collection.',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $category = Category::query()->firstOrCreate(
            ['slug' => $categorySlug],
            [
                'name' => $service->name,
                'section' => Product::SECTION_STUDIO,
                'is_active' => true,
            ]
        );
        $category->update([
            'section' => Product::SECTION_STUDIO,
            'is_active' => true,
        ]);

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => $service->name.' Public Piece',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        return $service;
    }

    /** @return array<string, string> */
    private function navigationSurfaces(string $html): array
    {
        return [
            'header' => $this->mustMatch('/<nav class="am-nav".*?<\/nav>/s', $html, 'desktop header'),
            'mobile' => $this->mustMatch('/<nav class="am-mobile-nav".*?<\/nav>/s', $html, 'mobile navigation'),
            'footer' => $this->mustMatch('/<footer class="am-footer".*?<\/footer>/s', $html, 'footer'),
        ];
    }

    private function assertUrlOnNavigationHomepageAndSitemap(string $url): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach ($this->navigationSurfaces($html) as $label => $surface) {
            $this->assertStringContainsString($url, $surface, "Expected {$url} in {$label}");
        }

        $this->assertStringContainsString($url, $html, 'Expected category URL on homepage');
        $this->assertStringContainsString($url, $this->get(route('sitemap'))->assertOk()->getContent());
    }

    private function assertUrlAbsentFromNavigationHomepageAndSitemap(string $url): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach ($this->navigationSurfaces($html) as $label => $surface) {
            $this->assertStringNotContainsString($url, $surface, "Did not expect {$url} in {$label}");
        }

        $this->assertStringNotContainsString($url, $html, 'Did not expect category URL on homepage');
        $this->assertStringNotContainsString($url, $this->get(route('sitemap'))->assertOk()->getContent());
    }

    private function assertNoGenericShopHref(string $html): void
    {
        preg_match_all('/href="([^"]+)"/i', $html, $matches);

        foreach ($matches[1] as $href) {
            $this->assertFalse(
                StorefrontNavigation::isGenericShopUrl($href),
                'Generic /shop href found: '.$href
            );
        }
    }

    /** @return list<string> */
    private function metaContents(string $html, string $name): array
    {
        preg_match_all(
            '/<meta\s+name="'.preg_quote($name, '/').'"\s+content="([^"]*)"/i',
            $html,
            $matches
        );

        return $this->decodeHtmlList($matches[1]);
    }

    /** @return list<string> */
    private function ogContents(string $html, string $property): array
    {
        preg_match_all(
            '/<meta\s+property="'.preg_quote($property, '/').'"\s+content="([^"]*)"/i',
            $html,
            $matches
        );

        return $this->decodeHtmlList($matches[1]);
    }

    /** @return list<string> */
    private function canonicalHrefs(string $html): array
    {
        preg_match_all('/<link\s+rel="canonical"\s+href="([^"]+)"/i', $html, $matches);

        return $matches[1];
    }

    private function mustMatch(string $pattern, string $html, string $label): string
    {
        $this->assertMatchesRegularExpression($pattern, $html, "Missing {$label} markup");
        preg_match($pattern, $html, $matches);

        return $matches[0];
    }

    /** @param list<string> $values @return list<string> */
    private function decodeHtmlList(array $values): array
    {
        return array_map(
            fn (string $value) => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $values
        );
    }
}
