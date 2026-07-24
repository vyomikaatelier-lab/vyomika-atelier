<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\UrlRedirect;
use App\Models\User;
use App\Support\Seo\JsonLd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SeoFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_excludes_draft_blogs_and_includes_canonical_pages(): void
    {
        BlogPost::query()->create([
            'title' => 'Draft SEO',
            'slug' => 'draft-seo-post',
            'excerpt' => 'x',
            'content' => 'y',
            'status' => 'draft',
            'is_active' => false,
            'published_at' => null,
        ]);

        BlogPost::query()->create([
            'title' => 'Live SEO',
            'slug' => 'live-seo-post',
            'excerpt' => 'x',
            'content' => 'y',
            'status' => 'published',
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();

        $this->assertStringContainsString(route('home'), $xml);
        $this->assertStringContainsString(route('railings.index'), $xml);
        $this->assertStringContainsString(route('corten-steel.show'), $xml);
        $this->assertStringContainsString('live-seo-post', $xml);
        $this->assertStringNotContainsString('draft-seo-post', $xml);
        $this->assertStringNotContainsString('/admin', $xml);
        $this->assertStringNotContainsString('/cart', $xml);
    }

    public function test_core_pages_have_title_canonical_and_h1(): void
    {
        foreach ([route('home'), route('railings.index'), route('corten-steel.show'), route('contact.index')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<title>.+<\/title>/i', $html);
            $this->assertStringContainsString('rel="canonical"', $html);
            preg_match_all('/<h1\b/i', $html, $matches);
            $this->assertGreaterThanOrEqual(1, count($matches[0]), 'Expected an H1 on '.$url);
        }

        foreach ([route('railings.index'), route('corten-steel.show')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match_all('/<h1\b/i', $html, $matches);
            $this->assertCount(1, $matches[0], 'Expected a single H1 on '.$url);
        }
    }

    public function test_cart_is_noindex(): void
    {
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_url_redirect_middleware_works(): void
    {
        UrlRedirect::query()->create([
            'from_path' => '/legacy-seo-test',
            'to_url' => '/contact',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/legacy-seo-test')->assertRedirect('/contact');
    }

    public function test_product_json_ld_only_for_checkout_shop_products(): void
    {
        $shop = Product::factory()->create([
            'section' => Product::SECTION_SHOP,
            'purchase_mode' => Product::PURCHASE_MODE_CHECKOUT,
            'pricing_type' => Product::PRICING_FIXED,
            'price' => 1500,
            'is_active' => true,
            'stock' => 2,
        ]);

        $studio = Product::factory()->create([
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_QUOTATION_ONLY,
            'is_active' => true,
        ]);

        $this->assertNotNull(JsonLd::product($shop));
        $this->assertNull(JsonLd::product($studio));
    }

    public function test_seo_installer_is_idempotent_and_preserves_admin_edits(): void
    {
        SiteSetting::setValue('static_pages', [
            'home' => ['meta_title' => 'Admin Locked Home Title'],
        ]);

        Artisan::call('seo:install-india-content');
        Artisan::call('seo:install-india-content');

        $pages = SiteSetting::getValue('static_pages', []);
        $this->assertSame('Admin Locked Home Title', data_get($pages, 'home.meta_title'));
        $this->assertNotEmpty(data_get($pages, 'home.meta_description'));

        $draftCount = BlogPost::query()->where('seo_source', 'india-seo-v1')->where('status', 'draft')->count();
        $this->assertSame(22, $draftCount);

        Artisan::call('seo:install-india-content');
        $this->assertSame(22, BlogPost::query()->where('seo_source', 'india-seo-v1')->count());
    }

    public function test_admin_can_edit_static_page_seo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->put(route('admin.static-pages.update', 'contact'), [
                'meta_title' => 'Contact SEO Title',
                'meta_description' => 'Contact meta description for testing.',
                'primary_keyword' => 'contact Vyomika Atelier',
                'robots' => 'index',
            ])
            ->assertRedirect();

        $this->assertSame('Contact SEO Title', data_get(SiteSetting::getValue('static_pages', []), 'contact.meta_title'));
    }

    public function test_draft_blog_is_not_public(): void
    {
        BlogPost::query()->create([
            'title' => 'Hidden',
            'slug' => 'hidden-draft',
            'excerpt' => 'x',
            'content' => 'y',
            'status' => 'draft',
            'is_active' => false,
            'seo_source' => 'india-seo-v1',
        ]);

        $this->get(route('blog.show', 'hidden-draft'))->assertNotFound();
    }
}
