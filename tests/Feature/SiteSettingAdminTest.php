<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
