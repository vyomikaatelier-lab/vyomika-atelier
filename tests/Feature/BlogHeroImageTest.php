<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogHeroImageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{image: string, og_image: string, alt: string, hero_wh: string}> */
    private function pillarFixtures(): array
    {
        return [
            'glass-partitions-open-plan' => [
                'image' => '/images/blog/heroes/glass-partitions-open-plan-hero.jpg',
                'og_image' => '/images/blog/heroes/glass-partitions-open-plan-hero-card.jpg',
                'alt' => 'Gold-finished metal and glass partition installed in a Vyomika Atelier living-room project',
                'hero_wh' => '768×1024',
            ],
            'pvd-coating-explained' => [
                'image' => '/images/blog/heroes/pvd-coating-explained-hero.jpg',
                'og_image' => '/images/blog/heroes/pvd-coating-explained-hero-card.jpg',
                'alt' => 'Close-up of a brushed gold PVD-coated metal plaque stencilled LED PROFILE PVD PARTITION, reflected on a glossy surface',
                'hero_wh' => '1024×682',
            ],
            'corten-steel-modern-facades' => [
                'image' => '/images/blog/heroes/corten-steel-modern-facades-hero.jpg',
                'og_image' => '/images/blog/heroes/corten-steel-modern-facades-hero.jpg',
                'alt' => 'Representative contemporary Indian building visualised with weathered Corten steel façade panels and perforated screens',
                'hero_wh' => '1024×576',
            ],
        ];
    }

    public function test_pillar_articles_expose_masthead_image_and_meta_not_full_width_banner(): void
    {
        foreach ($this->pillarFixtures() as $slug => $fixture) {
            BlogPost::create([
                'title' => 'Test '.$slug,
                'slug' => $slug,
                'excerpt' => 'Excerpt',
                'content' => '<p>Article body.</p>',
                'image' => $fixture['image'],
                'og_image' => $fixture['og_image'],
                'hero_image_alt' => $fixture['alt'],
                'status' => BlogPost::STATUS_PUBLISHED,
                'is_active' => true,
                'published_at' => now()->subDay(),
            ]);

            $html = $this->get(route('blog.show', $slug))->assertOk()->getContent();

            $this->assertStringNotContainsString('am-blog-article__hero', $html, $slug);
            $this->assertStringContainsString('article-masthead__media', $html, $slug.' masthead image');
            $this->assertStringContainsString('blog-image-frame', $html, $slug.' vertical frame');
            $this->assertStringContainsString('fetchpriority="high"', $html, $slug.' eager masthead load');
            $this->assertStringContainsString('property="og:image"', $html, $slug);
            $this->assertStringContainsString(
                asset(ltrim($fixture['og_image'], '/')),
                $html,
                $slug.' og:image'
            );
            $this->assertStringContainsString('BlogPosting', $html, $slug);
            $this->assertStringContainsString(
                ltrim($fixture['og_image'], '/'),
                $html,
                $slug.' schema image path'
            );
        }
    }

    public function test_blog_index_uses_vertical_frames_with_lazy_webp_pictures(): void
    {
        foreach ($this->pillarFixtures() as $slug => $fixture) {
            BlogPost::create([
                'title' => 'Test '.$slug,
                'slug' => $slug,
                'excerpt' => 'Excerpt',
                'content' => '<p>Article body.</p>',
                'image' => $fixture['image'],
                'hero_image_alt' => $fixture['alt'],
                'status' => BlogPost::STATUS_PUBLISHED,
                'is_active' => true,
                'published_at' => now()->subDay(),
            ]);
        }

        $html = $this->get(route('blog.index'))->assertOk()->getContent();

        $this->assertStringContainsString('blog-image-frame', $html);
        $this->assertStringContainsString('-hero.jpg', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertGreaterThan(0, substr_count($html, 'type="image/webp"'));
    }

    public function test_corten_alt_text_does_not_claim_vyomika_project_or_real_building(): void
    {
        $fixture = $this->pillarFixtures()['corten-steel-modern-facades'];

        BlogPost::create([
            'title' => 'Corten Test',
            'slug' => 'corten-steel-modern-facades',
            'content' => '<p>Body</p>',
            'image' => $fixture['image'],
            'hero_image_alt' => $fixture['alt'],
            'status' => BlogPost::STATUS_PUBLISHED,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $html = $this->get(route('blog.show', 'corten-steel-modern-facades'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Vyomika project', strtolower($html));
        $this->assertStringContainsString('BlogPosting', $html);
        $this->assertStringNotContainsString('am-blog-article__hero', $html);
        $this->assertStringContainsString('article-masthead__media', $html);
        $this->assertStringContainsString('blog-image-frame', $html);
    }
}
