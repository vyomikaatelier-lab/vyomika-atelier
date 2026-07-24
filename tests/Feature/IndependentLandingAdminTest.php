<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\CortenContent;
use App\Support\LandingPageContent;
use App\Support\MediaUrl;
use App\Support\RailingsContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'hero_cta_primary_label' => 'Request Quotation',
            'hero_cta_primary_href' => '#railing-quote',
            'intro_title' => 'Intro title override',
            'intro_body' => 'Intro body override',
            'section_title' => 'Railing Categories',
            'why_title' => 'Why us',
            'why_points' => "Point A\nPoint B",
            'quote_title' => 'Request a Quotation',
            'quote_body' => 'Tell us about your staircase',
            'cards' => [
                [
                    'title' => 'Glass Railings',
                    'text' => 'Toughened glass',
                    'image' => 'https://example.com/glass.jpg',
                    'image_alt' => 'Glass',
                    'cta_label' => 'Request Quote',
                    'cta_href' => '#railing-quote',
                    'active' => '1',
                ],
            ],
            'layouts' => [
                ['title' => 'Straight', 'text' => 'Single-run', 'active' => '1'],
            ],
        ]);

        $response->assertRedirect(route('admin.independent-pages.edit', 'railings'));

        $stored = SiteSetting::getValue('landing_pages', []);
        $this->assertSame('Updated Railings Hero', data_get($stored, 'railings.hero.title'));
        $this->assertSame('Custom Railings SEO', data_get($stored, 'railings.meta_title'));
        $this->assertSame('Glass Railings', data_get($stored, 'railings.categories.items.0.title'));
        $this->assertSame('Updated Railings Hero', RailingsContent::all()['hero']['title']);

        $this->get(route('railings.index'))
            ->assertOk()
            ->assertSee('Updated Railings Hero', false)
            ->assertSee('Glass Railings', false)
            ->assertSee('Straight', false)
            ->assertSee('Order Now', false)
            ->assertSee('href="#railing-quote-form"', false);
    }

    public function test_admin_can_update_corten_sections(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->put(route('admin.independent-pages.update', 'corten-steel'), [
            'hero_label' => 'Corten Steel',
            'hero_title' => 'Updated Corten Hero',
            'hero_subtitle' => 'Subtitle',
            'intro_title' => 'Intro',
            'intro_body' => 'Intro body',
            'section_title' => 'Applications',
            'why_title' => 'Why Choose',
            'why_points' => "Benefit one\nBenefit two",
            'finish_title' => 'Finish stages',
            'process_title' => 'Process',
            'process_steps' => "Step one\nStep two",
            'projects_title' => 'Projects',
            'technical_title' => 'Technical',
            'technical_options' => 'Option A',
            'considerations_title' => 'Planning',
            'considerations_points' => 'Plan well',
            'faq_title' => 'FAQ',
            'cta_title' => 'Final CTA',
            'cta_body' => 'Enquire now',
            'cta_form_label' => 'Enquiry label',
            'cta_form_title' => 'Enquiry title',
            'apps' => [
                ['name' => 'Building Facades', 'text' => 'Facades', 'image' => 'https://example.com/facade.jpg', 'image_alt' => 'Facade', 'active' => '1'],
                ['name' => 'Hidden App', 'active' => '0'],
            ],
            'stages' => [
                ['label' => 'Raw Steel', 'text' => 'Start', 'image' => 'https://example.com/raw.jpg', 'active' => '1'],
            ],
            'projects' => [
                ['title' => 'Featured One', 'location' => 'Delhi', 'category' => 'Facade', 'image' => 'https://example.com/p.jpg', 'active' => '1'],
            ],
            'faqs' => [
                ['q' => 'What is Corten?', 'a' => 'Weathering steel.', 'active' => '1'],
                ['q' => 'Hidden FAQ', 'a' => 'Nope', 'active' => '0'],
            ],
        ])->assertRedirect(route('admin.independent-pages.edit', 'corten-steel'));

        $this->assertSame('Updated Corten Hero', data_get(CortenContent::all(), 'hero.title'));

        $this->get(route('corten-steel.show'))
            ->assertOk()
            ->assertSee('Updated Corten Hero', false)
            ->assertSee('Building Facades', false)
            ->assertDontSee('Hidden App', false)
            ->assertSee('Raw Steel', false)
            ->assertSee('What is Corten?', false)
            ->assertDontSee('Hidden FAQ', false)
            ->assertSee('FAQPage', false)
            ->assertSee('Enquiry label', false)
            ->assertSee('Final CTA', false)
            ->assertSee('href="#corten-quote-form"', false)
            ->assertSee('Order Now', false);
    }

    public function test_inactive_gallery_cards_are_hidden_on_public_page(): void
    {
        SiteSetting::setValue('landing_pages', [
            'railings' => [
                'categories' => [
                    'title' => 'Cats',
                    'items' => [
                        ['title' => 'Visible Card', 'text' => 'A', 'active' => true],
                        ['title' => 'Hidden Card', 'text' => 'B', 'active' => false],
                    ],
                ],
            ],
        ]);

        $this->get(route('railings.index'))
            ->assertOk()
            ->assertSee('Visible Card', false)
            ->assertDontSee('Hidden Card', false);
    }

    public function test_media_url_resolves_storage_paths_and_https(): void
    {
        $this->assertSame(
            'https://cdn.example.com/a.jpg',
            MediaUrl::resolve('https://cdn.example.com/a.jpg')
        );
        $this->assertStringContainsString('storage/landing-pages/x.jpg', (string) MediaUrl::resolve('landing-pages/x.jpg'));
        $this->assertNull(MediaUrl::resolve('javascript:alert(1)'));
        $this->assertNull(MediaUrl::resolve('data:text/html,hi'));
    }

    public function test_admin_preview_resolves_storage_hero_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('landing-pages/hero.jpg', 'fake');

        SiteSetting::setValue('landing_pages', [
            'railings' => [
                'hero' => [
                    'title' => 'Hero',
                    'image' => 'landing-pages/hero.jpg',
                ],
            ],
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAsAdmin($admin)
            ->get(route('admin.independent-pages.edit', 'railings'))
            ->assertOk()
            ->assertSee('storage/landing-pages/hero.jpg', false);

        $this->get(route('railings.index'))
            ->assertOk()
            ->assertSee('storage/landing-pages/hero.jpg', false);
    }

    public function test_admin_can_upload_hero_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('hero.jpg', 800, 600);

        $this->actingAsAdmin($admin)->put(route('admin.independent-pages.update', 'corten-steel'), [
            'hero_title' => 'Corten Hero',
            'hero_image_file' => $file,
            'section_title' => 'Apps',
            'why_title' => 'Why',
            'why_points' => 'One',
            'apps' => [
                ['name' => 'Keep', 'active' => '1'],
            ],
        ])->assertRedirect();

        $path = data_get(SiteSetting::getValue('landing_pages', []), 'corten-steel.hero.image');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_invalid_upload_is_rejected_and_keeps_existing_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('landing-pages/keep.jpg', 'ok');
        SiteSetting::setValue('landing_pages', [
            'railings' => [
                'hero' => ['title' => 'Keep', 'image' => 'landing-pages/keep.jpg'],
            ],
        ]);

        $admin = User::factory()->admin()->create();
        $bad = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $this->actingAsAdmin($admin)->from(route('admin.independent-pages.edit', 'railings'))
            ->put(route('admin.independent-pages.update', 'railings'), [
                'hero_title' => 'Keep',
                'hero_image_file' => $bad,
                'section_title' => 'Cats',
                'cards' => [],
                'layouts' => [],
            ])
            ->assertSessionHasErrors('hero_image_file');

        $this->assertSame('landing-pages/keep.jpg', data_get(SiteSetting::getValue('landing_pages', []), 'railings.hero.image'));
    }

    public function test_independent_pages_require_admin_access_session(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withSession([AdminAccess::SESSION_KEY => false])
            ->get(route('admin.independent-pages.edit', 'railings'))
            ->assertRedirect();
    }

    public function test_landing_page_merge_replaces_item_lists(): void
    {
        $merged = LandingPageContent::mergePage(
            ['categories' => ['items' => [['title' => 'Old']]]],
            ['categories' => ['items' => [['title' => 'New']]]]
        );

        $this->assertCount(1, $merged['categories']['items']);
        $this->assertSame('New', $merged['categories']['items'][0]['title']);
    }

    public function test_admin_can_upload_responsive_hero_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $desktop = UploadedFile::fake()->image('hero-desktop.jpg', 1600, 900);
        $mobile = UploadedFile::fake()->image('hero-mobile.jpg', 800, 1200);

        $this->actingAsAdmin($admin)->put(route('admin.independent-pages.update', 'railings'), [
            'hero_title' => 'Responsive Hero',
            'hero_image_file' => $desktop,
            'hero_image_mobile_file' => $mobile,
            'section_title' => 'Cats',
            'cards' => [],
            'layouts' => [],
        ])->assertRedirect();

        $stored = data_get(SiteSetting::getValue('landing_pages', []), 'railings.hero');
        $this->assertNotEmpty($stored['image'] ?? null);
        $this->assertNotEmpty($stored['image_mobile'] ?? null);
        Storage::disk('public')->assertExists($stored['image']);
        Storage::disk('public')->assertExists($stored['image_mobile']);

        $this->get(route('railings.index'))
            ->assertOk()
            ->assertSee('--hero-bg-desktop:', false)
            ->assertSee('--hero-bg-mobile:', false);
    }

    public function test_corten_changes_persist_after_reload(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'hero_title' => 'Persisted Corten Title',
            'intro_title' => 'Persisted Intro',
            'intro_body' => 'Persisted intro body',
            'section_title' => 'Custom Applications',
            'why_title' => 'Why Corten',
            'why_points' => "Benefit A\nBenefit B",
            'finish_title' => 'Finish',
            'process_title' => 'Process',
            'process_steps' => "Step A\nStep B",
            'projects_title' => 'Projects',
            'technical_title' => 'Technical',
            'technical_options' => 'Option A',
            'considerations_title' => 'Planning',
            'considerations_points' => 'Plan well',
            'faq_title' => 'FAQ',
            'cta_title' => 'CTA',
            'cta_body' => 'Body',
            'apps' => [
                ['name' => 'Facades', 'text' => 'Facade copy', 'active' => '1'],
            ],
            'stages' => [
                ['label' => 'Raw', 'text' => 'Start', 'active' => '1'],
            ],
            'projects' => [
                ['title' => 'Project One', 'location' => 'Delhi', 'active' => '1'],
            ],
            'faqs' => [
                ['q' => 'Question?', 'a' => 'Answer.', 'active' => '1'],
            ],
        ];

        $this->actingAsAdmin($admin)
            ->put(route('admin.independent-pages.update', 'corten-steel'), $payload)
            ->assertRedirect(route('admin.independent-pages.edit', 'corten-steel'))
            ->assertSessionHas('success');

        $this->assertSame('Persisted Corten Title', data_get(CortenContent::all(), 'hero.title'));
        $this->assertSame('Facades', data_get(CortenContent::all(), 'applications.items.0.name'));

        $this->actingAsAdmin($admin)
            ->get(route('admin.independent-pages.edit', 'corten-steel'))
            ->assertOk()
            ->assertSee('Persisted Corten Title', false)
            ->assertSee('Facades', false)
            ->assertSee('Question?', false);

        $this->get(route('corten-steel.show'))
            ->assertOk()
            ->assertSee('Persisted Corten Title', false)
            ->assertSee('Facades', false);
    }

    public function test_corten_second_save_does_not_wipe_first_save(): void
    {
        $admin = User::factory()->admin()->create();
        $base = [
            'hero_title' => 'First Title',
            'section_title' => 'Apps',
            'why_title' => 'Why',
            'why_points' => 'One',
            'finish_title' => 'Finish',
            'process_title' => 'Process',
            'process_steps' => 'Step',
            'projects_title' => 'Projects',
            'technical_title' => 'Technical',
            'technical_options' => 'Option',
            'considerations_title' => 'Planning',
            'considerations_points' => 'Note',
            'faq_title' => 'FAQ',
            'cta_title' => 'CTA',
            'cta_body' => 'Body',
            'apps' => [['name' => 'Keep Me', 'active' => '1']],
            'stages' => [['label' => 'Stage', 'active' => '1']],
            'projects' => [['title' => 'Project', 'active' => '1']],
            'faqs' => [['q' => 'Q', 'a' => 'A', 'active' => '1']],
        ];

        $this->actingAsAdmin($admin)->put(route('admin.independent-pages.update', 'corten-steel'), $base);
        $this->actingAsAdmin($admin)->put(route('admin.independent-pages.update', 'corten-steel'), array_merge($base, [
            'hero_title' => 'Second Title',
        ]));

        $page = CortenContent::all();
        $this->assertSame('Second Title', data_get($page, 'hero.title'));
        $this->assertSame('Keep Me', data_get($page, 'applications.items.0.name'));
    }

    public function test_public_defaults_render_without_site_setting_override(): void
    {
        $this->get(route('railings.index'))
            ->assertOk()
            ->assertSee('Railings', false)
            ->assertSee('Balustrades', false)
            ->assertSee('Glass Railings', false)
            ->assertSee('href="#railing-quote-form"', false);

        $this->get(route('corten-steel.show'))
            ->assertOk()
            ->assertSee('Corten Steel Architectural Solutions', false)
            ->assertSee('Building Facades', false)
            ->assertSee('Frequently Asked Questions', false)
            ->assertSee('href="#corten-quote-form"', false);
    }
}
