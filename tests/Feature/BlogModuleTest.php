<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class BlogModuleTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function seedPublished(string $slug, array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'Post '.ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => 'Short excerpt for '.$slug,
            'content' => '<p>Body content with enough words to measure reading time for the article about metal fabrication and design.</p>',
            'category' => 'PVD Design',
            'author' => BlogPost::DEFAULT_AUTHOR,
            'hero_image_alt' => 'Hero alt for '.$slug,
            'is_active' => true,
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_blog_index_shows_published_posts_with_search_and_category_filters(): void
    {
        $this->seedPublished('alpha-post', ['category' => 'PVD Design']);
        $this->seedPublished('beta-post', ['category' => 'Doors']);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Journal', false)
            ->assertSee('Post Alpha post', false)
            ->assertSee('Post Beta post', false);

        $this->get(route('blog.index', ['category' => 'doors']))
            ->assertOk()
            ->assertSee('Post Beta post', false)
            ->assertDontSee('Post Alpha post', false);

        $this->get(route('blog.index', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Post Alpha post', false)
            ->assertDontSee('Post Beta post', false);
    }

    public function test_draft_and_future_scheduled_posts_are_hidden(): void
    {
        $this->seedPublished('visible-post');

        BlogPost::create([
            'title' => 'Draft Hidden',
            'slug' => 'draft-hidden',
            'content' => '<p>Draft</p>',
            'status' => BlogPost::STATUS_DRAFT,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        BlogPost::create([
            'title' => 'Future Scheduled',
            'slug' => 'future-scheduled',
            'content' => '<p>Scheduled</p>',
            'hero_image_alt' => 'Alt',
            'status' => BlogPost::STATUS_SCHEDULED,
            'is_active' => true,
            'published_at' => now()->addWeek(),
        ]);

        $this->get(route('blog.show', 'draft-hidden'))->assertNotFound();
        $this->get(route('blog.show', 'future-scheduled'))->assertNotFound();
        $this->get(route('blog.index'))->assertDontSee('Draft Hidden', false)->assertDontSee('Future Scheduled', false);
    }

    public function test_reading_time_is_auto_calculated_at_two_hundred_wpm(): void
    {
        $words = implode(' ', array_fill(0, 400, 'word'));
        $post = $this->seedPublished('reading-time-post', [
            'content' => '<p>'.$words.'</p>',
            'reading_time_minutes' => 99,
        ]);

        $this->assertSame(2, $post->fresh()->readingTime());
    }

    public function test_article_page_includes_schema_breadcrumbs_and_hides_empty_related_sections(): void
    {
        $post = $this->seedPublished('schema-post', [
            'content' => '<h2>One</h2><h2>Two</h2><h2>Three</h2><p>Body</p>',
            'faq' => [
                ['question' => 'What is PVD?', 'answer' => 'Physical Vapor Deposition finish.'],
                ['question' => '', 'answer' => 'ignored'],
            ],
        ]);

        $response = $this->get(route('blog.show', $post->slug))->assertOk();

        $response->assertSee('BlogPosting', false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertSee('FAQPage', false);
        $response->assertSee('In this article', false);
        $response->assertDontSee('Related Articles', false);
        $response->assertDontSee('Related Products', false);
    }

    public function test_related_articles_section_only_renders_with_cards(): void
    {
        $primary = $this->seedPublished('primary-post');
        $related = $this->seedPublished('related-one', ['category' => 'PVD Design']);

        $primary->update(['related_article_slugs' => [$related->slug]]);

        $this->get(route('blog.show', $primary->slug))
            ->assertOk()
            ->assertSee('Related Articles', false)
            ->assertSee('Post Related one', false);
    }

    public function test_admin_requires_auth_and_rejects_invalid_updates(): void
    {
        $admin = User::factory()->admin()->create();
        $post = $this->seedPublished('csrf-post');

        $this->delete(route('admin.blog.destroy', $post))->assertRedirect();

        $this->actingAsAdmin($admin)
            ->from(route('admin.blog.edit', $post))
            ->put(route('admin.blog.update', $post), [
                'title' => '',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('title');
    }

    public function test_admin_publish_requires_hero_alt_text(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->from(route('admin.blog.create'))
            ->post(route('admin.blog.store'), [
                'title' => 'Needs Alt',
                'status' => BlogPost::STATUS_PUBLISHED,
                'content' => '<p>Hello</p>',
            ])
            ->assertSessionHasErrors('hero_image_alt');
    }

    public function test_admin_slug_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedPublished('unique-slug');

        $this->actingAsAdmin($admin)
            ->from(route('admin.blog.create'))
            ->post(route('admin.blog.store'), [
                'title' => 'Another',
                'slug' => 'unique-slug',
                'status' => BlogPost::STATUS_DRAFT,
                'hero_image_alt' => 'Alt text here',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_only_one_featured_post_is_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->seedPublished('featured-one', ['is_featured' => true]);
        $second = $this->seedPublished('featured-two');

        $this->actingAsAdmin($admin)
            ->put(route('admin.blog.update', $second), [
                'title' => $second->title,
                'slug' => $second->slug,
                'status' => BlogPost::STATUS_PUBLISHED,
                'hero_image_alt' => 'Alt',
                'is_featured' => '1',
                'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }

    public function test_sitemap_excludes_drafts_and_unpublished_scheduled_posts(): void
    {
        $this->seedPublished('sitemap-live');
        BlogPost::create([
            'title' => 'Draft',
            'slug' => 'sitemap-draft',
            'content' => 'x',
            'status' => BlogPost::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $xml = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringContainsString('sitemap-live', $xml);
        $this->assertStringNotContainsString('sitemap-draft', $xml);
    }

    public function test_sync_trace_debug_markers_never_reach_public_announcement(): void
    {
        SiteSetting::setValue('homepage', [
            'announcement' => [
                'text' => 'SYNC-TRACE-8C03C3B-2026)',
                'link_label' => 'Trace',
                'link_href' => '/shop',
            ],
        ]);

        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('SYNC-TRACE', false);
    }

    public function test_categories_with_zero_posts_are_hidden_from_filters(): void
    {
        $this->seedPublished('only-pvd', ['category' => 'PVD Design']);

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('PVD Design', $html);
        $this->assertStringNotContainsString('Exhibitions', $html);
    }

    public function test_related_products_and_services_render_when_selected(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Partition Service',
            'slug' => 'partition-service-test',
            'is_active' => true,
        ]);
        $post = $this->seedPublished('relations-post', [
            'related_product_slugs' => [$product->slug],
            'related_service_slugs' => [$service->slug],
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee('Related Products', false)
            ->assertSee($product->name, false)
            ->assertSee('Related Studio Collections', false)
            ->assertSee($service->name, false);
    }

    public function test_pagination_preserves_category_and_search_query(): void
    {
        foreach (range(1, 10) as $i) {
            $this->seedPublished('paginate-post-'.$i, ['category' => 'PVD Design']);
        }

        $response = $this->get(route('blog.index', ['category' => 'pvd-design', 'q' => 'paginate', 'page' => 2]));
        $response->assertOk();
        $this->assertStringContainsString('category=pvd-design', $response->getContent());
        $this->assertStringContainsString('q=paginate', $response->getContent());
    }
}
