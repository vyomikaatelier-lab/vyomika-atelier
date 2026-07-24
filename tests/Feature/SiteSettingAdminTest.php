<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
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
}
