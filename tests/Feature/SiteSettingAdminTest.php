<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\CmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteSettingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_site_settings_with_post(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAsAdmin($admin)->post(route('admin.settings.update'), [
            'brand_name' => 'Vyomika Atelier LLP',
            'phone' => '+91 98188 91878',
            'email' => 'hello@vyomikaatelier.com',
            'instagram' => 'instagram.com/vyomikaatelier',
            'facebook' => '',
            'linkedin' => '',
            'pinterest' => '',
            'youtube' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(
            'https://instagram.com/vyomikaatelier',
            \App\Models\SiteSetting::getValue('social')['instagram'] ?? null
        );
    }

    public function test_admin_can_update_all_homepage_hero_slides(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $payload = [
            'brand_name' => 'Vyomika Atelier',
            'email' => 'hello@vyomikaatelier.com',
        ];

        foreach (array_keys(config('site.hero.slides', [])) as $index) {
            $payload['hero_slides'][$index] = [
                'kicker' => "Kicker {$index}",
                'title' => "Hero title {$index}",
                'description' => "Hero description {$index}",
                'cta_label' => 'Shop now',
                'cta_href' => '/shop',
            ];
        }

        $payload['hero_slides'][1]['image_file'] = UploadedFile::fake()->image('hero-1.jpg');

        $this->actingAsAdmin($admin)
            ->post(route('admin.settings.update'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $slides = SiteSetting::getValue('hero')['slides'] ?? [];
        $this->assertSame('Hero title 0', $slides[0]['title'] ?? null);
        $this->assertSame('Hero title 2', $slides[2]['title'] ?? null);
        $this->assertStringContainsString('hero/', $slides[1]['image'] ?? '');
    }

    public function test_admin_can_upload_mobile_and_tablet_hero_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)
            ->post(route('admin.settings.update'), [
                'brand_name' => 'Vyomika Atelier',
                'email' => 'hello@vyomikaatelier.com',
                'hero_slides' => [
                    0 => [
                        'title' => 'Responsive hero',
                        'image_file' => UploadedFile::fake()->image('hero-desktop.jpg'),
                        'image_mobile_file' => UploadedFile::fake()->image('hero-mobile.jpg'),
                        'image_tablet_file' => UploadedFile::fake()->image('hero-tablet.jpg'),
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $slide = SiteSetting::getValue('hero')['slides'][0] ?? [];
        $this->assertStringContainsString('hero/', $slide['image'] ?? '');
        $this->assertStringContainsString('hero/', $slide['image_mobile'] ?? '');
        $this->assertStringContainsString('hero/', $slide['image_tablet'] ?? '');
    }

    public function test_homepage_renders_responsive_hero_picture_sources(): void
    {
        SiteSetting::setValue('hero', [
            'slides' => [
                [
                    'title' => 'Responsive hero',
                    'image' => 'https://example.com/desktop.jpg',
                    'image_mobile' => 'https://example.com/mobile.jpg',
                    'image_tablet' => 'https://example.com/tablet.jpg',
                ],
            ],
        ]);
        CmsSettings::hydrate();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('media="(max-width: 767px)"', $html);
        $this->assertStringContainsString('https://example.com/mobile.jpg', $html);
        $this->assertStringContainsString('media="(max-width: 1023px)"', $html);
        $this->assertStringContainsString('https://example.com/tablet.jpg', $html);
        $this->assertStringContainsString('https://example.com/desktop.jpg', $html);
    }
}
