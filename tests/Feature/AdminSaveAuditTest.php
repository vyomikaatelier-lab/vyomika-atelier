<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Exhibition;
use App\Models\Lead;
use App\Models\LegalPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\UrlRedirect;
use App\Models\User;
use App\Support\LeadStatus;
use App\Support\PageHeroContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSaveAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_exhibition_deactivates_when_active_checkbox_unchecked(): void
    {
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'design-week',
            'name' => 'Design Week',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.exhibitions.update', $exhibition), [
            '_page_save' => '1',
            'name' => 'Design Week',
            'gallery_managed' => '1',
            'sort_order' => 1,
        ])->assertRedirect(route('admin.exhibitions.index'));

        $this->assertFalse($exhibition->fresh()->is_active);
    }

    public function test_exhibition_validation_errors_are_shown(): void
    {
        $admin = User::factory()->admin()->create();

        $exhibition = Exhibition::query()->create([
            'slug' => 'design-week',
            'name' => 'Design Week',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->from(route('admin.exhibitions.edit', $exhibition))
            ->put(route('admin.exhibitions.update', $exhibition), [
                '_page_save' => '1',
                'name' => '',
                'gallery_managed' => '1',
            ])
            ->assertRedirect(route('admin.exhibitions.edit', $exhibition))
            ->assertSessionHasErrors('name');
    }

    public function test_category_deactivates_when_active_checkbox_unchecked(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'section' => 'shop',
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.categories.update', $category), [
            '_page_save' => '1',
            'name' => 'Test Category',
            'section' => 'shop',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_blog_deactivates_when_visible_checkbox_unchecked(): void
    {
        $admin = User::factory()->admin()->create();
        $post = BlogPost::query()->create([
            'title' => 'Audit Post',
            'slug' => 'audit-post',
            'status' => 'published',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.blog.update', $post), [
            '_page_save' => '1',
            'title' => 'Audit Post',
            'status' => 'published',
        ])->assertRedirect(route('admin.blog.index'));

        $this->assertFalse($post->fresh()->is_active);
    }

    public function test_product_deactivates_when_active_checkbox_unchecked(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->firstOrCreate(
            ['slug' => 'partitions'],
            ['name' => 'Partitions', 'section' => 'studio', 'is_active' => true]
        );

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Audit Product',
            'slug' => 'audit-product',
            'price' => 1000,
            'stock' => 1,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.products.update', $product), [
            '_page_save' => '1',
            'category_id' => $category->id,
            'name' => 'Audit Product',
            'slug' => 'audit-product',
            'price' => 1000,
            'stock' => 1,
            'section' => Product::SECTION_STUDIO,
            'purchase_mode' => Product::PURCHASE_MODE_ENQUIRY,
            'pricing_type' => Product::PRICING_SQUARE_FOOT,
        ])->assertRedirect(route('admin.products.edit', ['product' => $product, 'saved' => 1]));

        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_url_redirect_deactivates_when_checkbox_unchecked(): void
    {
        $admin = User::factory()->admin()->create();

        UrlRedirect::query()->create([
            'from_path' => '/old-path',
            'to_url' => '/contact',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->actingAsAdmin($admin)->post(route('admin.redirects.store'), [
            'from_path' => '/old-path',
            'to_url' => '/contact',
            'status_code' => 301,
        ])->assertRedirect();

        $this->assertFalse(UrlRedirect::query()->where('from_path', '/old-path')->value('is_active'));
    }

    public function test_customer_can_be_disabled_via_select(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['is_active' => true]);

        $this->actingAsAdmin($admin)->put(route('admin.customers.update', $customer), [
            'name' => $customer->name,
            'email' => $customer->email,
            'mobile' => $customer->mobile,
            'account_type' => $customer->account_type,
            'is_active' => '0',
            'current_password' => 'password',
        ])->assertRedirect();

        $this->assertFalse($customer->fresh()->is_active);
    }

    public function test_legal_page_update_persists(): void
    {
        $admin = User::factory()->admin()->create();
        $page = LegalPage::query()->create([
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'sections' => [['heading' => 'Intro', 'paragraphs' => ['Original']]],
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.legal.update', $page), [
            'title' => 'Privacy Policy Updated',
            'meta_title' => 'Privacy SEO',
            'section_headings' => ['Data collection'],
            'section_paragraphs' => ["We collect only what we need.\nNo resale."],
        ])->assertRedirect(route('admin.legal.index'));

        $page->refresh();
        $this->assertSame('Privacy Policy Updated', $page->title);
        $this->assertSame('Data collection', $page->sections[0]['heading']);
    }

    public function test_order_status_update_persists(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '9999999999',
            'shipping_address' => '123 Test Street',
            'city' => 'Mumbai',
            'pincode' => '400001',
            'subtotal' => 1000,
            'shipping_cost' => 199,
            'total' => 1199,
            'status' => 'pending',
            'payment_method' => 'razorpay',
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.orders.update', $order), [
            'status' => 'processing',
            'admin_notes' => 'Packed for dispatch',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('Packed for dispatch', $order->admin_notes);
    }

    public function test_lead_status_update_persists(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
            'type' => 'contact',
            'message' => 'Hello',
            'status' => LeadStatus::NEW,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.leads.update', $lead), [
            'status' => LeadStatus::QUALIFIED,
            'admin_notes' => 'Good fit',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame(LeadStatus::QUALIFIED, $lead->status);
        $this->assertSame('Good fit', $lead->admin_notes);
    }

    public function test_professional_application_update_persists(): void
    {
        $admin = User::factory()->admin()->create();
        $application = Lead::create([
            'name' => 'Architect',
            'email' => 'arch@example.com',
            'type' => 'professional_application',
            'message' => 'Portfolio attached',
            'status' => LeadStatus::NEW,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.professional-applications.update', $application), [
            'status' => LeadStatus::QUALIFIED,
            'admin_notes' => 'Approved for trade pricing',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame(LeadStatus::QUALIFIED, $application->status);
    }

    public function test_railing_quote_update_persists(): void
    {
        $admin = User::factory()->admin()->create();
        $quote = Lead::create([
            'name' => 'Homeowner',
            'email' => 'home@example.com',
            'type' => 'railing_quotation',
            'message' => 'Staircase quote',
            'status' => LeadStatus::NEW,
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.railing-quotes.update', $quote), [
            'status' => LeadStatus::QUOTATION_SENT,
            'admin_notes' => 'Sent PDF quote',
        ])->assertRedirect();

        $quote->refresh();
        $this->assertSame(LeadStatus::QUOTATION_SENT, $quote->status);
    }

    public function test_page_hero_update_persists(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $slug = PageHeroContent::slugs()[0];

        $this->actingAsAdmin($admin)->put(route('admin.page-heroes.update', $slug), [
            '_page_save' => '1',
            'hero_title' => 'Hero audit title',
        ])->assertRedirect(route('admin.page-heroes.index'));

        $stored = SiteSetting::getValue('page_heroes', []);
        $this->assertSame('Hero audit title', data_get($stored, "{$slug}.title"));
    }
}
