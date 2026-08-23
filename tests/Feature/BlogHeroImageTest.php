<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogHeroImageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{image: string, og_image: string, alt: string, hero_wh: string, card_wh: string}> */
    private function pillarFixtures(): array
    {
        return [
            'glass-partitions-open-plan' => [
                'image' => '/images/blog/heroes/glass-partitions-open-plan-hero.jpg',
                'og_image' => '/images/blog/heroes/glass-partitions-open-plan-hero-card.jpg',
                'alt' => 'Gold-finished metal and glass partition installed in a Vyomika Atelier living-room project',
                'hero_wh' => '768×1024',
                'card_wh' => '768×432',
            ],
            'pvd-coating-explained' => [
                'image' => '/images/blog/heroes/pvd-coating-explained-hero.jpg',
                'og_image' => '/images/blog/heroes/pvd-coating-explained-hero-card.jpg',
                'alt' => 'Close-up of a brushed gold PVD-coated metal plaque stencilled LED PROFILE PVD PARTITION, reflected on a glossy surface',
                'hero_wh' => '1024×682',
                'card_wh' => '1024×576',
            ],
            'corten-steel-modern-facades' => [
                'image' => '/images/blog/heroes/corten-steel-modern-facades-hero.jpg',
                'og_image' => '/images/blog/heroes/corten-steel-modern-facades-hero.jpg',
                'alt' => 'Representative contemporary Indian building visualised with weathered Corten steel façade panels and perforated screens',
                'hero_wh' => '1024×576',
                'card_wh' => '1024×576',
            ],
        ];
    }

    public function test_pillar_article_pages_render_webp_picture_hero_with_explicit_dimensions(): void
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

            $this->assertStringContainsString('<picture>', $html, $slug);
            $this->assertStringContainsString('type="image/webp"', $html, $slug);
            $this->assertStringContainsString('.webp', $html, $slug);
            [$heroW, $heroH] = array_map('trim', explode('×', $fixture['hero_wh']));
            $this->assertStringContainsString('width="'.$heroW.'"', $html, $slug);
            $this->assertStringContainsString('height="'.$heroH.'"', $html, $slug);
            $this->assertStringContainsString('loading="eager"', $html, $slug);
            $this->assertStringContainsString('fetchpriority="high"', $html, $slug);
            $this->assertStringContainsString($fixture['alt'], $html, $slug);
            $this->assertStringContainsString(
                asset(ltrim($fixture['og_image'], '/')),
                $html,
                $slug.' og:image'
            );
        }
    }

    public function test_blog_index_uses_card_crops_with_lazy_webp_pictures(): void
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

        $this->assertStringContainsString('-hero-card.jpg', $html);
        $this->assertStringContainsString('-hero-card.webp', $html);
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

        $this->assertStringContainsString('Representative', $html);
        $this->assertStringContainsString('visualised', $html);
        $this->assertStringNotContainsString('Vyomika project', strtolower($html));
    }
}
