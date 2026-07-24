<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\RailingsContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndependentLandingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_railings_page_with_admin_access(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAsAdmin($admin)->put(route('admin.independent-pages.update', 'railings'), [
            'meta_title' => 'Custom Railings SEO',
            'meta_description' => 'Updated railings meta',
            'hero_title' => 'Updated Railings Hero',
            'hero_subtitle' => 'Hero subtitle override',
            'intro_title' => 'Intro title override',
            'intro_body' => 'Intro body override',
        ]);

        $response->assertRedirect(route('admin.independent-pages.index'));

        $stored = SiteSetting::getValue('landing_pages', []);
        $this->assertSame('Updated Railings Hero', data_get($stored, 'railings.hero.title'));
        $this->assertSame('Custom Railings SEO', data_get($stored, 'railings.meta_title'));
        $this->assertSame('Updated Railings Hero', RailingsContent::all()['hero']['title']);
    }

    public function test_independent_pages_require_admin_access_session(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withSession([AdminAccess::SESSION_KEY => false])
            ->get(route('admin.independent-pages.edit', 'railings'))
            ->assertRedirect();
    }
}
