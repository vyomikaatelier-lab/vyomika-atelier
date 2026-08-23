<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_page_has_compact_masthead_not_full_width_hero(): void
    {
        $post = BlogPost::create([
            'title' => 'Design Test Article',
            'slug' => 'design-test-article',
            'excerpt' => 'Testing editorial layout.',
            'content' => '<h2>Section One</h2><p>Body text here.</p><h2>Section Two</h2><p>More body.</p><h2>Section Three</h2><p>Final section.</p>',
            'image' => '/images/blog/heroes/pvd-coating-explained-hero.jpg',
            'hero_image_alt' => 'PVD coating close-up on metal',
            'status' => BlogPost::STATUS_PUBLISHED,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $html = $this->get(route('blog.show', $post->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('am-blog-article__hero', $html);
        $this->assertStringContainsString('article-masthead', $html);
        $this->assertStringContainsString('article-masthead__media', $html);
        $this->assertStringContainsString('blog-image-frame', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('am-blog-article__layout', $html);
        $this->assertStringContainsString('am-blog-article__divider', $html);
        $this->assertStringContainsString('am-breadcrumbs--compact', $html);
        $this->assertStringContainsString('In this article', $html);
    }

    public function test_blog_index_renders_vertical_image_frames(): void
    {
        BlogPost::create([
            'title' => 'Frame Test',
            'slug' => 'frame-test',
            'excerpt' => 'Excerpt',
            'content' => '<p>Body</p>',
            'image' => '/images/blog/heroes/glass-partitions-open-plan-hero.jpg',
            'hero_image_alt' => 'Glass partition hero',
            'status' => BlogPost::STATUS_PUBLISHED,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('blog-image-frame', $html);
        $this->assertStringContainsString('am-blog-index', $html);
        $this->assertStringContainsString('am-blog-featured', $html);
    }

    public function test_related_articles_use_vertical_frames(): void
    {
        $related = BlogPost::create([
            'title' => 'Related Frame Test',
            'slug' => 'related-frame-test',
            'content' => '<p>Related body</p>',
            'image' => '/images/blog/heroes/pvd-coating-explained-hero.jpg',
            'hero_image_alt' => 'Related hero alt text here',
            'status' => BlogPost::STATUS_PUBLISHED,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $primary = BlogPost::create([
            'title' => 'Primary With Related',
            'slug' => 'primary-with-related',
            'content' => '<p>Primary body</p>',
            'image' => '/images/blog/heroes/corten-steel-modern-facades-hero.jpg',
            'hero_image_alt' => 'Primary hero alt',
            'related_article_slugs' => [$related->slug],
            'status' => BlogPost::STATUS_PUBLISHED,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $html = $this->get(route('blog.show', $primary->slug))->assertOk()->getContent();

        $this->assertStringContainsString('Related Articles', $html);
        $this->assertStringContainsString('blog-image-frame', $html);
        $this->assertStringContainsString('Related Frame Test', $html);
    }
}
