<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThirdPartyHostRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_git_tracked_tree_contains_no_banned_third_party_host(): void
    {
        $needle = $this->bannedHostNeedle();
        $lines = [];
        exec(
            'git -C '.escapeshellarg(base_path()).' grep -i -I -n -- '.escapeshellarg($needle).' 2>&1',
            $lines
        );

        $this->assertSame(
            [],
            $lines,
            'Tracked tree still contains the banned host:'.PHP_EOL.implode(PHP_EOL, $lines)
        );
    }

    public function test_site_config_has_a_single_local_hero_slide(): void
    {
        $slides = config('site.hero.slides', []);

        $this->assertCount(1, $slides);
        $this->assertSame(
            '/images/blog/heroes/glass-partitions-open-plan-hero-card.jpg',
            $slides[0]['image'] ?? null
        );
        $this->assertSame('Define Spaces With PVD Partitions', $slides[0]['title'] ?? null);
    }

    public function test_homepage_renders_one_hero_slide_without_carousel_dots_or_empty_image_urls(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase($this->bannedHostNeedle(), $html);
        preg_match_all('/class="am-hero__slide(?:\s|")/', $html, $heroSlides);
        $this->assertCount(1, $heroSlides[0]);
        $this->assertStringNotContainsString('am-hero__dot', $html);
        $this->assertStringNotContainsString('Wave & Fluted Metal Dividers', $html);
        $this->assertStringNotContainsString('Bespoke Tables & Rack Systems', $html);
        $this->assertStringContainsString('Define Spaces With PVD Partitions', $html);
        $this->assertStringContainsString('glass-partitions-open-plan-hero-card.jpg', $html);
        $this->assertNoBrokenContentImageUrls($html);
    }

    public function test_service_project_and_blog_fallbacks_render_without_empty_image_urls(): void
    {
        $service = Service::query()->updateOrCreate(
            ['slug' => 'rack-systems-metal-pvd'],
            [
                'name' => 'Rack Systems, Metal PVD',
                'summary' => 'Display racks.',
                'image' => '',
                'lead_form' => 'popup',
                'is_active' => true,
            ]
        );

        $category = Category::query()->updateOrCreate(
            ['slug' => 'rack-systems-metal-pvd'],
            [
                'name' => 'Metal PVD Rack Systems',
                'section' => Product::SECTION_STUDIO,
                'is_active' => true,
            ]
        );

        Product::factory()->studio()->create([
            'category_id' => $category->id,
            'name' => 'Wall Display Rack',
            'image' => '',
            'is_active' => true,
            'is_gallery_visible' => true,
        ]);

        Project::query()->create([
            'project_name' => 'Rose Gold Retail Showroom',
            'work_type' => 'commercial',
            'city' => 'Bangalore',
            'description' => 'Room dividers for a luxury retail environment.',
            'image_path' => '',
            'display_order' => 1,
            'is_active' => true,
        ]);

        BlogPost::query()->create([
            'title' => 'Decorative Metal Screens',
            'slug' => 'decorative-laser-cut-metal-screens',
            'excerpt' => 'Laser-cut decorative metal screens.',
            'content' => '<p>Screens for privacy and branding.</p>',
            'image' => '',
            'status' => BlogPost::STATUS_PUBLISHED,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $serviceHtml = $this->get(route('studio.show', 'metal-pvd-rack-systems'))->assertOk()->getContent();
        $projectHtml = $this->get(route('projects.index'))->assertOk()->getContent();
        $blogHtml = $this->get(route('blog.show', 'decorative-laser-cut-metal-screens'))->assertOk()->getContent();

        foreach ([$serviceHtml, $projectHtml, $blogHtml] as $html) {
            $this->assertStringNotContainsStringIgnoringCase($this->bannedHostNeedle(), $html);
            $this->assertNoBrokenContentImageUrls($html);
        }

        $this->assertStringContainsString($service->name, $serviceHtml);
        $this->assertStringContainsString('Rose Gold Retail Showroom', $projectHtml);
        $this->assertStringContainsString('am-work-gallery__media--empty', $projectHtml);
        $this->assertStringContainsString('Decorative Metal Screens', $blogHtml);
    }

    public function test_public_json_fallbacks_remain_valid_and_omit_empty_gallery_urls(): void
    {
        foreach (['data/site-content.json', 'data/blog.json'] as $relative) {
            $decoded = json_decode((string) file_get_contents(public_path($relative)), true);
            $this->assertIsArray($decoded, $relative);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), $relative);
        }

        $site = json_decode((string) file_get_contents(public_path('data/site-content.json')), true);
        $this->assertCount(1, $site['hero']['slides'] ?? []);

        $this->assertSame(
            '',
            data_get($site, 'featured_product.image')
        );

        $this->assertNoEmptyGalleryUrls($site);
        $this->assertNoEmptyGalleryUrls(
            json_decode((string) file_get_contents(public_path('data/blog.json')), true)
        );
    }

    public function test_static_preview_homepage_omits_empty_images_and_single_slide_dots(): void
    {
        $html = $this->renderStaticPreviewHomepage();
        $site = json_decode((string) file_get_contents(public_path('data/site-content.json')), true);
        $heroImage = (string) data_get($site, 'hero.slides.0.image');

        $this->assertStringContainsString('Define Spaces With PVD Partitions', $html);
        $this->assertNotSame('', trim($heroImage));
        $this->assertStringContainsString($heroImage, $html);
        $this->assertStringContainsString('Laser-Cut Partition', $html);
        $this->assertStringContainsString('Fluted Panels', $html);
        $this->assertStringContainsString('am-product-card', $html);
        $this->assertStringContainsString('am-cat-tile', $html);
        $this->assertStringNotContainsString('am-hero__dot', $html);
        $this->assertStringNotContainsString('am-hero__dots', $html);
        $this->assertNoBrokenContentImageUrls($html);
        $this->assertDoesNotMatchRegularExpression('/<img\b[^>]*\bsrc\s*=\s*(["\'])\s+\1/i', $html);
    }

    public function test_static_preview_preserves_multi_slide_dots_for_two_local_heroes(): void
    {
        $site = json_decode((string) file_get_contents(public_path('data/site-content.json')), true);
        $this->assertIsArray($site);
        $slide = $site['hero']['slides'][0];
        $site['hero']['slides'][] = $slide;

        $html = $this->renderStaticPreviewHomepage($site);

        preg_match_all('/class="am-hero__dot(?:\s|")/', $html, $dots);
        $this->assertCount(2, $dots[0]);
        $this->assertStringContainsString('am-hero__dots', $html);
        $this->assertNoBrokenContentImageUrls($html);
    }

    public function test_static_preview_whitespace_only_images_do_not_emit_src_or_url(): void
    {
        $site = json_decode((string) file_get_contents(public_path('data/site-content.json')), true);
        $this->assertIsArray($site);
        $site['trending']['products'][0]['image'] = '   ';
        $site['category_banners'][1]['image'] = "\t";
        $site['hero']['slides'][0]['image'] = ' ';

        $html = $this->renderStaticPreviewHomepage($site);

        $this->assertStringContainsString('Laser-Cut Partition', $html);
        $this->assertStringContainsString('Fluted Panels', $html);
        $this->assertStringContainsString('Define Spaces With PVD Partitions', $html);
        $this->assertNoBrokenContentImageUrls($html);
        $this->assertDoesNotMatchRegularExpression('/<img\b[^>]*\bsrc\s*=\s*(["\'])\s+\1/i', $html);
    }

    public function test_static_preview_router_guards_empty_product_and_gallery_images(): void
    {
        $api = $this->evalStaticPreviewRouterApi();

        $this->assertFalse($api['usableEmpty']);
        $this->assertFalse($api['usableSpaces']);
        $this->assertTrue($api['usableLocal']);

        $this->assertStringContainsString('am-pdp__main', $api['pdpEmpty']);
        $this->assertStringNotContainsString('<img', $api['pdpEmpty']);
        $this->assertStringContainsString('am-pdp__main', $api['pdpSpaces']);
        $this->assertStringNotContainsString('<img', $api['pdpSpaces']);
        $this->assertStringContainsString('<img', $api['pdpLocal']);
        $this->assertStringContainsString('glass-partitions-open-plan-hero-card.jpg', $api['pdpLocal']);

        $this->assertSame('', $api['bgEmpty']);
        $this->assertSame('', $api['bgSpaces']);
        $this->assertSame('', $api['imgEmpty']);
        $this->assertSame('', $api['imgSpaces']);
        $this->assertSame(['/images/a.jpg'], $api['gallery']);

        $this->assertNoBrokenContentImageUrls($api['pdpEmpty'].$api['pdpSpaces'].$api['pdpLocal']);
    }

    private function bannedHostNeedle(): string
    {
        return implode('', ['delhi', 'duniya']);
    }

    private function assertNoBrokenContentImageUrls(string $html): void
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $matches);

        foreach ($matches[0] as $tag) {
            if ($this->isJavascriptImagePlaceholder($tag)) {
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

        $this->assertDoesNotMatchRegularExpression('/url\(\s*([\'"]?)\s*\1\s*\)/i', $html);
    }

    private function isJavascriptImagePlaceholder(string $tag): bool
    {
        return (bool) preg_match(
            '/\b(?:data-qv-img|id="am-work-lightbox-img"|id="am-about-lightbox-img"|am-work-lightbox__img|am-about-lightbox__img)\b/i',
            $tag
        );
    }

    private function assertNoEmptyGalleryUrls(mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        if (array_is_list($value) && $value !== [] && array_key_exists('gallery', $value) === false) {
            foreach ($value as $item) {
                $this->assertNoEmptyGalleryUrls($item);
            }

            return;
        }

        if (isset($value['gallery']) && is_array($value['gallery'])) {
            foreach ($value['gallery'] as $url) {
                $this->assertTrue(
                    is_string($url) && $url !== '',
                    'Empty gallery URL retained in public JSON'
                );
            }
        }

        foreach ($value as $item) {
            $this->assertNoEmptyGalleryUrls($item);
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function renderStaticPreviewHomepage(?array $data = null): string
    {
        $root = base_path();
        $payloadPath = null;
        if ($data !== null) {
            $payloadPath = tempnam(sys_get_temp_dir(), 'am-preview-data-');
            file_put_contents($payloadPath, json_encode($data, JSON_UNESCAPED_SLASHES));
        }

        $script = <<<'JS'
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = process.argv[2];
const override = process.argv[3] || '';
const store = { innerHTML: '' };
const sandbox = {
  console,
  window: { AmPreviewRouter: true },
  document: {
    readyState: 'complete',
    getElementById: () => store,
    documentElement: { dataset: { hero: 'fullscreen' } },
    dispatchEvent: () => {},
    addEventListener: () => {},
  },
  XMLHttpRequest: function () { this.open = function () {}; this.send = function () {}; },
  CustomEvent: function () {},
};
sandbox.window.document = sandbox.document;
const code = fs.readFileSync(path.join(root, 'public/js/preview-render.js'), 'utf8');
vm.runInNewContext(code, sandbox);
const dataPath = override !== '' ? override : path.join(root, 'public/data/site-content.json');
sandbox.window.AmPreview.render(JSON.parse(fs.readFileSync(dataPath, 'utf8')));
process.stdout.write(store.innerHTML);
JS;

        try {
            return $this->runNodeScript($script, array_values(array_filter([$root, $payloadPath])));
        } finally {
            if ($payloadPath !== null && is_file($payloadPath)) {
                unlink($payloadPath);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function evalStaticPreviewRouterApi(): array
    {
        $script = <<<'JS'
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = process.argv[2];
const sandbox = {
  console,
  window: {
    addEventListener: function () {},
  },
  document: {
    readyState: 'loading',
    getElementById: () => ({ innerHTML: '' }),
    querySelector: () => null,
    documentElement: { dataset: {} },
    dispatchEvent: () => {},
    addEventListener: () => {},
  },
  XMLHttpRequest: function () { this.open = function () {}; this.send = function () {}; },
  CustomEvent: function () {},
};
sandbox.window.document = sandbox.document;
vm.runInNewContext(fs.readFileSync(path.join(root, 'public/js/preview-router.js'), 'utf8'), sandbox);
const api = sandbox.window.AmPreviewRouterApi;
process.stdout.write(JSON.stringify({
  usableEmpty: api.usablePreviewImage(''),
  usableSpaces: api.usablePreviewImage('   '),
  usableLocal: api.usablePreviewImage('/images/blog/heroes/glass-partitions-open-plan-hero-card.jpg'),
  pdpEmpty: api.pdpMainHtml('', 'Champagne Wave Partition'),
  pdpSpaces: api.pdpMainHtml('   ', 'Veil Fluted Panel'),
  pdpLocal: api.pdpMainHtml('/images/blog/heroes/glass-partitions-open-plan-hero-card.jpg', 'Local slide'),
  bgEmpty: api.previewBackgroundStyle('--hero', ''),
  bgSpaces: api.previewBackgroundStyle('--hero', '  '),
  imgEmpty: api.previewImgHtml('', 'alt'),
  imgSpaces: api.previewImgHtml(' \n ', 'alt'),
  gallery: api.previewGalleryUrls(['', '  ', '/images/a.jpg']),
}));
JS;

        $decoded = json_decode($this->runNodeScript($script, [base_path()]), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param  list<string>  $args
     */
    private function runNodeScript(string $script, array $args): string
    {
        $scriptPath = tempnam(sys_get_temp_dir(), 'am-preview-js-');
        file_put_contents($scriptPath, $script);

        $command = 'node '.escapeshellarg($scriptPath);
        foreach ($args as $arg) {
            $command .= ' '.escapeshellarg($arg);
        }

        $output = [];
        $exit = 1;
        exec($command.' 2>&1', $output, $exit);
        unlink($scriptPath);

        $joined = implode("\n", $output);
        $this->assertSame(0, $exit, $joined);

        return $joined;
    }
}
