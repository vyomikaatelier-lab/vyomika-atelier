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

    public function test_admin_can_edit_independent_landing_page_seo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->put(route('admin.independent-pages.update', 'railings'), [
                '_landing_save' => '1',
                'meta_title' => 'Railings SEO Title',
                'meta_description' => 'Railings meta description for testing.',
                'og_title' => 'Railings OG Title',
                'og_description' => 'Railings OG description.',
                'og_image' => 'https://example.com/railings-og.jpg',
                'canonical' => route('railings.index'),
                'primary_keyword' => 'stainless railings India',
                'seo_keyword' => 'internal railings keyword',
                'robots' => 'index',
            ])
            ->assertRedirect();

        $stored = SiteSetting::getValue('landing_pages', [])['railings'] ?? [];
        $this->assertSame('Railings SEO Title', $stored['meta_title'] ?? null);
        $this->assertSame('Railings OG Title', $stored['og_title'] ?? null);
        $this->assertSame('internal railings keyword', $stored['seo_keyword'] ?? null);

        $html = $this->get(route('railings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<title>Railings SEO Title</title>', $html);
        $this->assertStringContainsString('name="description" content="Railings meta description for testing."', $html);
        $this->assertStringContainsString('property="og:title" content="Railings OG Title"', $html);
        $this->assertStringContainsString('property="og:description" content="Railings OG description."', $html);
        $this->assertStringContainsString('property="og:image" content="https://example.com/railings-og.jpg"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.route('railings.index').'"', $html);
    }

    public function test_about_and_projects_render_full_seo_meta(): void
    {
        SiteSetting::setValue('static_pages', [
            'home' => [
                'meta_title' => 'Home SEO Title',
                'meta_description' => 'Home meta description.',
                'og_title' => 'Home OG Title',
                'og_description' => 'Home OG description.',
                'og_image' => 'https://example.com/home-og.jpg',
                'canonical' => route('home'),
                'robots' => 'index',
            ],
            'about' => [
                'meta_title' => 'About SEO Title',
                'meta_description' => 'About meta description.',
                'og_title' => 'About OG Title',
                'og_description' => 'About OG description.',
                'og_image' => 'https://example.com/about-og.jpg',
                'robots' => 'index',
            ],
            'projects' => [
                'meta_title' => 'Projects SEO Title',
                'meta_description' => 'Projects meta description.',
                'og_title' => 'Projects OG Title',
                'robots' => 'index',
            ],
        ]);

        $homeHtml = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('<title>Home SEO Title</title>', $homeHtml);
        $this->assertStringContainsString('name="description" content="Home meta description."', $homeHtml);
        $this->assertStringContainsString('property="og:title" content="Home OG Title"', $homeHtml);
        $this->assertStringContainsString('property="og:description" content="Home OG description."', $homeHtml);
        $this->assertStringContainsString('property="og:image" content="https://example.com/home-og.jpg"', $homeHtml);
        $this->assertStringContainsString('rel="canonical" href="'.route('home').'"', $homeHtml);
        $this->assertSame(1, substr_count($homeHtml, 'rel="canonical"'), 'Homepage should have a single canonical tag');
        $this->assertSame(1, substr_count($homeHtml, 'name="description"'), 'Homepage should have a single meta description');

        $aboutHtml = $this->get(route('about'))->assertOk()->getContent();
        $this->assertStringContainsString('<title>About SEO Title</title>', $aboutHtml);
        $this->assertStringContainsString('property="og:title" content="About OG Title"', $aboutHtml);
        $this->assertStringContainsString('property="og:image" content="https://example.com/about-og.jpg"', $aboutHtml);

        $projectsHtml = $this->get(route('projects.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<title>Projects SEO Title</title>', $projectsHtml);
        $this->assertStringContainsString('property="og:title" content="Projects OG Title"', $projectsHtml);
    }

    public function test_landing_gallery_pages_render_item_list_json_ld(): void
    {
        SiteSetting::setValue('landing_pages', [
            'railings' => [
                'categories' => [
                    'title' => 'Railing Designs',
                    'items' => [
                        [
                            'title' => 'Glass Railings',
                            'text' => 'Frameless glass with stainless posts.',
                            'meta_title' => 'Glass Railings India',
                            'meta_description' => 'Designer glass railing fabrication.',
                            'image' => 'https://example.com/glass.jpg',
                            'active' => true,
                        ],
                    ],
                ],
            ],
            'corten-steel' => [
                'applications' => [
                    'title' => 'Corten Applications',
                    'items' => [
                        [
                            'name' => 'Decorative Screens',
                            'text' => 'Laser-cut corten screens.',
                            'meta_title' => 'Corten Decorative Screens',
                            'meta_description' => 'Outdoor corten screen fabrication.',
                            'image' => 'https://example.com/screen.jpg',
                            'active' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $railingsHtml = $this->get(route('railings.index'))->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"ItemList"', $railingsHtml);
        $this->assertStringContainsString('Glass Railings India', $railingsHtml);
        $this->assertStringContainsString('Designer glass railing fabrication.', $railingsHtml);

        $cortenHtml = $this->get(route('corten-steel.show'))->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"ItemList"', $cortenHtml);
        $this->assertStringContainsString('Corten Decorative Screens', $cortenHtml);
        $this->assertStringContainsString('Outdoor corten screen fabrication.', $cortenHtml);
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

    public function test_admin_can_edit_homepage_seo_via_site_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->post(route('admin.settings.update'), [
                'current_password' => 'password',
                'brand_name' => 'Vyomika Atelier',
                'meta_title' => 'Site Settings Home Title',
                'meta_description' => 'Homepage description from site settings.',
                'og_title' => 'Site Settings Home OG',
                'og_description' => 'Homepage OG description from site settings.',
                'og_image' => 'https://example.com/site-settings-home.jpg',
                'canonical' => route('home'),
                'robots' => 'index',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $stored = SiteSetting::getValue('static_pages', [])['home'] ?? [];
        $this->assertSame('Site Settings Home Title', $stored['meta_title'] ?? null);
        $this->assertSame('Site Settings Home OG', $stored['og_title'] ?? null);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('<title>Site Settings Home Title</title>', $html);
        $this->assertStringContainsString('name="description" content="Homepage description from site settings."', $html);
        $this->assertStringContainsString('property="og:title" content="Site Settings Home OG"', $html);
        $this->assertStringContainsString('property="og:image" content="https://example.com/site-settings-home.jpg"', $html);
        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertSame(1, substr_count($html, 'name="description"'));
    }

    public function test_blog_index_renders_static_page_seo_without_duplicate_meta(): void
    {
        SiteSetting::setValue('static_pages', [
            'blog' => [
                'meta_title' => 'Blog SEO Title',
                'meta_description' => 'Blog index meta description.',
                'og_title' => 'Blog OG Title',
                'og_description' => 'Blog OG description.',
                'canonical' => route('blog.index'),
                'robots' => 'index',
            ],
        ]);

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<title>Blog SEO Title</title>', $html);
        $this->assertStringContainsString('name="description" content="Blog index meta description."', $html);
        $this->assertStringContainsString('property="og:title" content="Blog OG Title"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.route('blog.index').'"', $html);
        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertSame(1, substr_count($html, 'name="description"'));
    }

    public function test_published_blog_post_renders_full_seo_meta(): void
    {
        BlogPost::query()->create([
            'title' => 'PVD Partition Guide',
            'slug' => 'pvd-partition-guide',
            'excerpt' => 'How to specify PVD partitions for Indian projects.',
            'content' => '<p>Guide content.</p>',
            'meta_title' => 'PVD Partition Guide | Vyomika Atelier',
            'meta_description' => 'Specify PVD partitions with confidence.',
            'og_title' => 'PVD Partition Guide OG',
            'og_description' => 'OG description for PVD guide.',
            'og_image' => 'https://example.com/pvd-guide.jpg',
            'canonical_url' => route('blog.show', 'pvd-partition-guide'),
            'status' => 'published',
            'is_active' => true,
            'published_at' => now()->subDay(),
            'robots_index' => true,
        ]);

        $html = $this->get(route('blog.show', 'pvd-partition-guide'))->assertOk()->getContent();

        $this->assertStringContainsString('<title>PVD Partition Guide | Vyomika Atelier</title>', $html);
        $this->assertStringContainsString('name="description" content="Specify PVD partitions with confidence."', $html);
        $this->assertStringContainsString('property="og:title" content="PVD Partition Guide OG"', $html);
        $this->assertStringContainsString('property="og:description" content="OG description for PVD guide."', $html);
        $this->assertStringContainsString('property="og:image" content="https://example.com/pvd-guide.jpg"', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);
        $this->assertStringContainsString('rel="canonical" href="'.route('blog.show', 'pvd-partition-guide').'"', $html);
        $this->assertStringContainsString('property="article:published_time"', $html);
        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertSame(1, substr_count($html, 'name="description"'));
    }
}
