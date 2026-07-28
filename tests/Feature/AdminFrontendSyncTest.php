<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\LegalPage;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Support\CmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end checks that an admin edit is what the public page renders, i.e.
 * the database is the source of truth rather than a config default.
 */
class AdminFrontendSyncTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_project_edit_appears_on_the_public_project_page(): void
    {
        Storage::fake('public');

        $project = Project::query()->create([
            'title' => 'Andheri Loft',
            'slug' => 'andheri-loft',
            'summary' => 'Old summary',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.projects.update', $project), [
                '_page_save' => '1',
                'title' => 'Andheri Loft',
                'summary' => 'A brass and glass mezzanine study.',
                'location' => 'Mumbai',
                'is_active' => '1',
                'display_order' => 1,
            ])
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('projects.show', 'andheri-loft'))
            ->assertOk()
            ->assertSee('A brass and glass mezzanine study.', false);

        $this->get(route('projects.index'))->assertOk()->assertSee('Andheri Loft');
    }

    public function test_deactivating_a_project_removes_it_from_the_storefront(): void
    {
        $project = Project::query()->create([
            'title' => 'Andheri Loft',
            'slug' => 'andheri-loft',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.projects.update', $project), [
                '_page_save' => '1',
                'title' => 'Andheri Loft',
                'display_order' => 1,
            ])
            ->assertRedirect(route('admin.projects.index'));

        $this->assertFalse($project->fresh()->is_active);
        $this->get(route('projects.show', 'andheri-loft'))->assertNotFound();
        $this->get(route('projects.index'))->assertOk()->assertDontSee('Andheri Loft');
    }

    public function test_blog_edit_appears_on_the_public_post(): void
    {
        $post = BlogPost::query()->create([
            'title' => 'Living With Brass',
            'slug' => 'living-with-brass',
            'excerpt' => 'Old excerpt',
            'status' => 'published',
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.blog.update', $post), [
                '_page_save' => '1',
                'title' => 'Living With Brass',
                'excerpt' => 'Patina, care and pairing notes.',
                'status' => 'published',
                'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.blog.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('blog.show', 'living-with-brass'))
            ->assertOk()
            ->assertSee('Patina, care and pairing notes.', false);
    }

    public function test_service_edit_appears_on_the_public_service_page(): void
    {
        $service = Service::query()->create([
            'name' => 'Fluted Screens',
            'slug' => 'fluted-screens',
            'summary' => 'Old summary',
            'lead_form' => 'popup',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.services.update', $service), [
                '_page_save' => '1',
                'name' => 'Fluted Screens',
                'slug' => 'fluted-screens',
                'summary' => 'Hand-finished fluted metal screens.',
                'lead_form' => 'popup',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.services.edit', ['service' => $service, 'saved' => 1]))
            ->assertSessionHasNoErrors();

        $this->get(route('services.show', 'fluted-screens'))
            ->assertOk()
            ->assertSee('Hand-finished fluted metal screens.', false);

        $this->get(route('services.index'))->assertOk()->assertSee('Fluted Screens');
    }

    public function test_exhibition_edit_appears_on_the_about_page(): void
    {
        $exhibition = Exhibition::query()->create([
            'slug' => 'india-design-id',
            'name' => 'India Design ID',
            'city' => 'New Delhi',
            'year' => 2026,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.exhibitions.update', $exhibition), [
                '_page_save' => '1',
                'name' => 'India Design ID',
                'city' => 'New Delhi',
                'year' => 2026,
                'description' => 'Our debut showcase of PVD partitions.',
                'gallery_managed' => '1',
                'sort_order' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.exhibitions.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('India Design ID');
    }

    public function test_legal_page_edit_appears_on_the_public_policy_page(): void
    {
        // The storefront resolves legal pages by the short config key.
        LegalPage::query()->updateOrCreate(
            ['slug' => 'privacy'],
            [
                'title' => 'Privacy Policy',
                'sections' => [['heading' => 'Intro', 'paragraphs' => ['Original']]],
            ]
        );

        $page = LegalPage::query()->where('slug', 'privacy')->firstOrFail();

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.legal.update', $page), [
                'title' => 'Privacy Policy',
                'section_headings' => ['Data we collect'],
                'section_paragraphs' => ['Only what is needed to fulfil your order.'],
            ])
            ->assertRedirect(route('admin.legal.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Data we collect')
            ->assertSee('Only what is needed to fulfil your order.', false);
    }

    public function test_brand_settings_appear_in_the_storefront_layout(): void
    {
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.settings.update'), [
                'brand_name' => 'Vyomika Atelier',
                'phone' => '9812345678',
                'email' => 'hello@vyomika.test',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        CmsSettings::hydrate();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('hello@vyomika.test', false);
    }

    public function test_static_page_seo_edit_appears_in_page_meta(): void
    {
        $this->actingAsAdmin($this->admin())
            ->put(route('admin.static-pages.update', 'about'), [
                'meta_title' => 'About Vyomika Atelier — Metal Craft Studio',
                'meta_description' => 'Our studio, our makers, our finishes.',
                'robots' => 'index',
            ])
            ->assertRedirect(route('admin.static-pages.edit', 'about'))
            ->assertSessionHasNoErrors();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('About Vyomika Atelier — Metal Craft Studio', false)
            ->assertSee('Our studio, our makers, our finishes.', false);
    }

    public function test_page_hero_edit_appears_on_the_about_page(): void
    {
        Storage::fake('public');

        $this->actingAsAdmin($this->admin())
            ->put(route('admin.page-heroes.update', 'about'), [
                '_page_save' => '1',
                'hero_title' => 'Metal, made deliberately',
                'hero_subtitle' => 'A studio practice in brass, steel and stone.',
            ])
            ->assertRedirect(route('admin.page-heroes.edit', ['slug' => 'about', 'saved' => 1]))
            ->assertSessionHasNoErrors();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Metal, made deliberately', false);
    }
}
