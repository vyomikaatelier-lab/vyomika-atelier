<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Support\CmsSettings;
use App\Support\ResponsiveHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomepageMobileLcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_lcp_hero_is_eager_with_fetchpriority_srcset_sizes_and_dimensions(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $hero = $this->heroPictureHtml($html);

        $this->assertNotSame('', $hero);
        $this->assertStringContainsString('fetchpriority="high"', $hero);
        $this->assertStringNotContainsString('loading="lazy"', $hero);
        $this->assertStringContainsString('srcset="', $hero);
        $this->assertStringContainsString('sizes="(max-width: 1024px) 100vw, 50vw"', $hero);
        $this->assertStringContainsString('width="768"', $hero);
        $this->assertStringContainsString('height="432"', $hero);
        $this->assertStringContainsString('glass-partitions-open-plan-hero-card.jpg', $hero);
        $this->assertStringContainsString('glass-partitions-open-plan-hero-card.webp', $hero);
        $this->assertStringContainsString('type="image/webp"', $hero);

        preg_match_all('/(?:src|srcset)="([^"]+)"/', $hero, $urls);
        $this->assertNotEmpty($urls[1]);
        foreach ($urls[1] as $url) {
            $this->assertFalse((bool) preg_match('#^https?://#i', $url) && ! preg_match('#^https?://(?:localhost|127\.0\.0\.1|vyomikaatelier\.com)#i', $url), $url);
            $this->assertTrue(
                str_contains($url, '/images/') || str_contains($url, '/storage/'),
                $url
            );
        }
    }

    public function test_single_slide_hero_has_no_dots_and_js_does_not_start_a_timer(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/class="am-hero__slide(?:\s|")/', $html, $slides);
        $this->assertCount(1, $slides[0]);
        $this->assertStringNotContainsString('am-hero__dot', $html);
        $this->assertStringNotContainsString('am-hero__dots', $html);

        $js = (string) file_get_contents(public_path('js/amerce.js'));
        $this->assertStringContainsString('if (slides.length < 2)', $js);
        $this->assertStringContainsString('setInterval(next, 6000)', $js);
    }

    public function test_font_preconnect_and_direct_stylesheet_replace_css_import(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $css = (string) file_get_contents(public_path('css/amerce.css'));

        $this->assertStringContainsString('rel="preconnect" href="https://fonts.googleapis.com"', $html);
        $this->assertStringContainsString('rel="preconnect" href="https://fonts.gstatic.com" crossorigin', $html);
        $this->assertStringContainsString('https://fonts.googleapis.com/css2?family=DM+Sans', $html);
        $this->assertStringContainsString('family=Playfair+Display', $html);
        $this->assertStringNotContainsString("@import url('https://fonts.googleapis.com", $css);
        $this->assertStringContainsString('css/amerce.css?v=', $html);
    }

    public function test_cms_hero_preload_uses_mobile_tablet_and_desktop_candidates_not_config_fallback(): void
    {
        $slide = [
            'title' => 'Define Spaces With PVD Partitions',
            'image' => 'https://example.com/cms-desktop.webp',
            'image_mobile' => 'https://example.com/cms-mobile.webp',
            'image_tablet' => 'https://example.com/cms-tablet.webp',
        ];
        SiteSetting::setValue('hero', ['slides' => [$slide]]);
        CmsSettings::hydrate();

        $html = $this->get(route('home'))->assertOk()->getContent();
        $hero = $this->heroPictureHtml($html);
        $preloads = $this->imagePreloadTags($html);

        $this->assertStringContainsString('https://example.com/cms-mobile.webp', $hero);
        $this->assertStringContainsString('https://example.com/cms-tablet.webp', $hero);
        $this->assertStringContainsString('https://example.com/cms-desktop.webp', $hero);
        $this->assertStringContainsString('type="image/webp"', $hero);
        $this->assertStringContainsString('fetchpriority="high"', $hero);
        $this->assertStringContainsString('sizes="(max-width: 1024px) 100vw, 50vw"', $hero);
        $this->assertStringNotContainsString('glass-partitions-open-plan-hero-card', $hero);

        $joined = implode("\n", $preloads);
        $this->assertStringContainsString('https://example.com/cms-mobile.webp', $joined);
        $this->assertStringContainsString('https://example.com/cms-tablet.webp', $joined);
        $this->assertStringContainsString('https://example.com/cms-desktop.webp', $joined);
        $this->assertStringContainsString('media="(max-width: 767px)"', $joined);
        $this->assertStringContainsString('media="(min-width: 768px) and (max-width: 1023px)"', $joined);
        $this->assertStringContainsString('media="(min-width: 1024px)"', $joined);
        $this->assertStringNotContainsString('glass-partitions-open-plan-hero-card', $joined);

        $this->assertPreloadMatchesPicture($slide);
        $this->assertHtmlPreloadMatchesHeroPicture($html);
    }

    #[DataProvider('heroPreloadCombinations')]
    public function test_preload_matches_picture_for_each_viewport(array $hero, array $expectedPreloads): void
    {
        $preloads = ResponsiveHero::preloadLinks($hero);
        $this->assertSame($expectedPreloads, array_map(
            fn (array $link) => ['href' => $link['href'], 'media' => $link['media']],
            $preloads
        ));
        $this->assertPreloadMatchesPicture($hero);
    }

    public function test_homepage_below_fold_images_have_dimensions_and_lazy_loading(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('width="768"', $html);
        $this->assertStringContainsString('height="1024"', $html);
        $this->assertNoBrokenContentImageUrls($html);
    }

    public function test_empty_hero_image_values_do_not_emit_src_or_url(): void
    {
        $this->assertNull(ResponsiveHero::picture(['image' => ' ']));
        $this->assertNull(ResponsiveHero::picture([
            'image' => "\t",
            'image_mobile' => '',
            'image_tablet' => '   ',
        ]));
        $this->assertSame([], ResponsiveHero::preloadLinks(['image' => '']));

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertNoBrokenContentImageUrls($html);
    }

    public function test_homepage_hero_upload_rules_accept_webp(): void
    {
        foreach (ResponsiveHero::flatValidationRules('hero') as $rule) {
            if (is_string($rule) && str_contains($rule, 'mimes:')) {
                $this->assertStringContainsString('webp', $rule);
            }
        }

        $controller = (string) file_get_contents(app_path('Http/Controllers/Admin/SiteSettingAdminController.php'));
        $this->assertStringContainsString("'nullable|image|mimes:jpeg,jpg,png,webp|max:5120'", $controller);
        $this->assertStringContainsString('image_mobile_file', $controller);
        $this->assertStringContainsString('image_tablet_file', $controller);
    }

    /** @return array<string, array{0: array<string, string>, 1: list<array{href: string, media: ?string}>}> */
    public static function heroPreloadCombinations(): array
    {
        return [
            'all distinct' => [
                [
                    'image' => 'https://example.com/cms-desktop.webp',
                    'image_tablet' => 'https://example.com/cms-tablet.webp',
                    'image_mobile' => 'https://example.com/cms-mobile.webp',
                ],
                [
                    ['href' => 'https://example.com/cms-mobile.webp', 'media' => '(max-width: 767px)'],
                    ['href' => 'https://example.com/cms-tablet.webp', 'media' => '(min-width: 768px) and (max-width: 1023px)'],
                    ['href' => 'https://example.com/cms-desktop.webp', 'media' => '(min-width: 1024px)'],
                ],
            ],
            'all identical' => [
                [
                    'image' => 'https://example.com/same.webp',
                    'image_tablet' => 'https://example.com/same.webp',
                    'image_mobile' => 'https://example.com/same.webp',
                ],
                [
                    ['href' => 'https://example.com/same.webp', 'media' => null],
                ],
            ],
            'mobile distinct tablet equals desktop' => [
                [
                    'image' => 'https://example.com/wide.webp',
                    'image_tablet' => 'https://example.com/wide.webp',
                    'image_mobile' => 'https://example.com/phone.webp',
                ],
                [
                    ['href' => 'https://example.com/phone.webp', 'media' => '(max-width: 767px)'],
                    ['href' => 'https://example.com/wide.webp', 'media' => '(min-width: 768px)'],
                ],
            ],
            'mobile equals tablet desktop distinct' => [
                [
                    'image' => 'https://example.com/wide.webp',
                    'image_tablet' => 'https://example.com/narrow.webp',
                    'image_mobile' => 'https://example.com/narrow.webp',
                ],
                [
                    ['href' => 'https://example.com/narrow.webp', 'media' => '(max-width: 1023px)'],
                    ['href' => 'https://example.com/wide.webp', 'media' => '(min-width: 1024px)'],
                ],
            ],
            'cascade absent mobile tablet' => [
                [
                    'image' => 'https://example.com/fallback-desktop.webp',
                ],
                [
                    ['href' => 'https://example.com/fallback-desktop.webp', 'media' => null],
                ],
            ],
        ];
    }

    private function heroPictureHtml(string $html): string
    {
        if (! preg_match('/<section class="am-hero">[\s\S]*?<picture>[\s\S]*?<\/picture>/', $html, $match)) {
            return '';
        }

        return $match[0];
    }

    /** @return list<string> */
    private function imagePreloadTags(string $html): array
    {
        preg_match_all('/<link\b[^>]*>/i', $html, $matches);
        $tags = [];

        foreach ($matches[0] as $tag) {
            if (str_contains($tag, 'rel="preload"') && str_contains($tag, 'as="image"')) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @param  array<string, mixed>  $hero
     */
    private function assertPreloadMatchesPicture(array $hero, ?string $fallbackDesktop = null): void
    {
        $picture = ResponsiveHero::picture($hero, $fallbackDesktop);
        $preloads = ResponsiveHero::preloadLinks($hero, $fallbackDesktop);

        $this->assertNotNull($picture);
        $this->assertNotSame([], $preloads);

        foreach ([390, 767, 768, 820, 1023, 1024, 1440] as $width) {
            $matches = 0;
            foreach ($preloads as $link) {
                if ($this->mediaMatches($link['media'] ?? null, $width)) {
                    $matches++;
                }
            }

            $this->assertSame(1, $matches, 'preload gap or overlap at '.$width.'px');
            $this->assertSame(
                $this->displayedHeroUrl($picture, $width),
                $this->preloadedHeroUrl($preloads, $width),
                'viewport '.$width.'px'
            );
        }
    }

    private function assertHtmlPreloadMatchesHeroPicture(string $html): void
    {
        $hero = $this->heroPictureHtml($html);
        $this->assertNotSame('', $hero);

        preg_match_all('/<source\b[^>]*>/i', $hero, $sourceTags);
        $sources = [];
        foreach ($sourceTags[0] as $tag) {
            if (preg_match('/\bmedia="([^"]*)"/i', $tag, $media) && preg_match('/\bsrcset="([^"]*)"/i', $tag, $srcset)) {
                $sources[] = ['media' => $media[1], 'srcset' => $srcset[1]];
            }
        }

        preg_match('/<img\b[^>]*>/i', $hero, $imgTag);
        $this->assertNotEmpty($imgTag);
        preg_match('/\bsrc="([^"]*)"/i', $imgTag[0], $imgSrc);

        $preloads = [];
        foreach ($this->imagePreloadTags($html) as $tag) {
            preg_match('/\bhref="([^"]*)"/i', $tag, $href);
            $media = preg_match('/\bmedia="([^"]*)"/i', $tag, $m) ? $m[1] : null;
            $preloads[] = ['href' => $href[1] ?? '', 'media' => $media];
        }

        foreach ([390, 767, 768, 820, 1023, 1024, 1440] as $width) {
            $displayed = null;
            foreach ($sources as $source) {
                if ($this->mediaMatches($source['media'], $width)) {
                    $displayed = $source['srcset'];
                    break;
                }
            }
            $displayed ??= $imgSrc[1] ?? null;

            $preloaded = null;
            $matches = 0;
            foreach ($preloads as $link) {
                if ($this->mediaMatches($link['media'], $width)) {
                    $matches++;
                    $preloaded ??= $link['href'];
                }
            }

            $this->assertSame(1, $matches, 'HTML preload gap or overlap at '.$width.'px');
            $this->assertSame($displayed, $preloaded, 'HTML viewport '.$width.'px');
        }
    }

    /** @param  array{src: string, sources: list<array{media: string, srcset: string, type: ?string}>}  $picture */
    private function displayedHeroUrl(array $picture, int $width): string
    {
        foreach ($picture['sources'] as $source) {
            if ($this->mediaMatches($source['media'] ?? null, $width)) {
                return $source['srcset'];
            }
        }

        return $picture['src'];
    }

    /** @param  list<array{href: string, media: ?string, type: ?string}>  $preloads */
    private function preloadedHeroUrl(array $preloads, int $width): ?string
    {
        foreach ($preloads as $link) {
            if ($this->mediaMatches($link['media'] ?? null, $width)) {
                return $link['href'];
            }
        }

        return null;
    }

    private function mediaMatches(?string $media, int $width): bool
    {
        if ($media === null || $media === '') {
            return true;
        }

        $ok = true;
        if (preg_match('/min-width:\s*(\d+)px/', $media, $min)) {
            $ok = $ok && $width >= (int) $min[1];
        }
        if (preg_match('/max-width:\s*(\d+)px/', $media, $max)) {
            $ok = $ok && $width <= (int) $max[1];
        }

        return $ok;
    }

    private function assertNoBrokenContentImageUrls(string $html): void
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $matches);

        foreach ($matches[0] as $tag) {
            if (preg_match('/\b(?:data-qv-img|id="am-work-lightbox-img"|id="am-about-lightbox-img"|am-work-lightbox__img|am-about-lightbox__img)\b/i', $tag)) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/\bsrc\s*=\s*(["\'])\s*\1/i',
                $tag,
                'Empty content image src: '.$tag
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\bsrcset\s*=\s*(["\'])\s*\1/i',
                $tag,
                'Empty content image srcset: '.$tag
            );
        }
    }
}
