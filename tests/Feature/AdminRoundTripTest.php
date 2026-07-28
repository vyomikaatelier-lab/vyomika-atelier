<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Order;
use App\Models\UrlRedirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saves a change through the admin form and then re-reads the page the admin
 * lands on, which is where "I saved it but it went back to the old value" would
 * show up. Modules whose save path is already covered elsewhere are skipped.
 */
class AdminRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_order_status_and_notes_are_shown_again_after_saving(): void
    {
        $order = Order::query()->create([
            'order_number' => 'VA-ROUNDTRIP',
            'customer_name' => 'Riya Menon',
            'customer_email' => 'riya@example.test',
            'customer_phone' => '9876543210',
            'shipping_address' => '12 Carter Road',
            'city' => 'Mumbai',
            'pincode' => '400050',
            'subtotal' => 12000,
            'total' => 12000,
            'status' => 'pending',
            'payment_method' => 'razorpay',
        ]);

        $admin = $this->admin();

        $this->actingAsAdmin($admin)
            ->put(route('admin.orders.update', $order), [
                'status' => 'shipped',
                'admin_notes' => 'Dispatched via Blue Dart, AWB 88991122.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $this->assertSame('Dispatched via Blue Dart, AWB 88991122.', $order->admin_notes);

        $this->actingAsAdmin($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Dispatched via Blue Dart, AWB 88991122.', false);
    }

    public function test_customer_edits_are_shown_again_after_saving(): void
    {
        $customer = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'mobile' => '9811111111',
            'is_admin' => false,
            'is_active' => true,
        ]);

        $admin = $this->admin();

        $this->actingAsAdmin($admin)
            ->put(route('admin.customers.update', $customer), [
                'name' => 'Aarav Sharma',
                'email' => 'aarav@example.test',
                'mobile' => '9822222222',
                'account_type' => array_key_first(User::ACCOUNT_TYPES),
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Aarav Sharma', $customer->name);
        $this->assertSame('aarav@example.test', $customer->email);
        $this->assertSame('9822222222', $customer->mobile);

        $this->actingAsAdmin($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Aarav Sharma')
            ->assertSee('aarav@example.test');
    }

    public function test_collection_page_text_is_shown_again_and_reaches_the_storefront(): void
    {
        $admin = $this->admin();

        $this->actingAsAdmin($admin)
            ->put(route('admin.collection-pages.update', 'coffee-tables'), [
                '_page_save' => '1',
                'hero_title' => 'Coffee tables, welded by hand',
                'hero_subtitle' => 'Brass and blackened steel bases.',
                'intro_title' => 'Made to your room',
                'intro_body' => 'Every top is cut to the millimetre.',
                'gallery_title' => 'Recent tables',
                'meta_title' => 'Coffee Tables — Vyomika Atelier',
                'meta_description' => 'Hand-welded coffee tables in brass and steel.',
            ])
            ->assertRedirect(route('admin.collection-pages.edit', ['slug' => 'coffee-tables', 'saved' => 1]))
            ->assertSessionHasNoErrors();

        $this->actingAsAdmin($admin)
            ->get(route('admin.collection-pages.edit', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Coffee tables, welded by hand', false)
            ->assertSee('Every top is cut to the millimetre.', false);

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertSee('Coffee tables, welded by hand', false)
            ->assertSee('Coffee Tables — Vyomika Atelier', false);
    }

    public function test_clearing_collection_page_text_does_not_restore_the_config_default(): void
    {
        $admin = $this->admin();

        $this->actingAsAdmin($admin)
            ->put(route('admin.collection-pages.update', 'coffee-tables'), [
                '_page_save' => '1',
                'hero_title' => 'Temporary headline',
                'intro_title' => 'Temporary intro',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAsAdmin($admin)
            ->put(route('admin.collection-pages.update', 'coffee-tables'), [
                '_page_save' => '1',
                'hero_title' => 'Temporary headline',
                'intro_title' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->get(route('shop.show', 'coffee-tables'))
            ->assertOk()
            ->assertDontSee('Temporary intro', false);
    }

    public function test_redirect_created_in_admin_takes_effect_immediately(): void
    {
        $this->actingAsAdmin($this->admin())
            ->post(route('admin.redirects.store'), [
                'from_path' => '/old-mirror-page',
                'to_url' => '/shop/mirror-frames',
                'status_code' => '301',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $redirect = UrlRedirect::query()->firstOrFail();
        $this->assertTrue($redirect->is_active);
        $this->assertSame(301, (int) $redirect->status_code);

        $this->get('/old-mirror-page')->assertRedirect('/shop/mirror-frames');
    }

    public function test_deleting_a_redirect_stops_it_immediately(): void
    {
        $redirect = UrlRedirect::query()->create([
            'from_path' => '/retired-page',
            'to_url' => '/shop',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/retired-page')->assertRedirect('/shop');

        $this->actingAsAdmin($this->admin())
            ->delete(route('admin.redirects.destroy', $redirect))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, UrlRedirect::query()->count());
        $this->get('/retired-page')->assertNotFound();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function leadActions(): array
    {
        return [
            'mark spam' => ['admin.leads.mark-spam', 'spam_suspected'],
            'mark vendor' => ['admin.leads.mark-vendor', 'marketing_vendor'],
        ];
    }

    /** @dataProvider leadActions */
    public function test_lead_queue_action_persists(string $route, string $expectedStatus): void
    {
        $lead = Lead::query()->create([
            'name' => 'Vendor Pitch',
            'email' => 'pitch@example.test',
            'phone' => '9876500000',
            'message' => 'We supply hardware.',
            'type' => 'inquiry',
            'status' => 'new',
        ]);

        $this->actingAsAdmin($this->admin())
            ->post(route($route, $lead))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($expectedStatus, $lead->fresh()->status?->value ?? $lead->fresh()->status);
    }

    public function test_lead_can_be_restored_to_the_review_queue(): void
    {
        $lead = Lead::query()->create([
            'name' => 'False Positive',
            'email' => 'real@example.test',
            'phone' => '9876511111',
            'message' => 'Please quote a PVD partition.',
            'type' => 'inquiry',
            'status' => 'spam_suspected',
            'notifications_suppressed' => true,
        ]);

        $this->actingAsAdmin($this->admin())
            ->post(route('admin.leads.restore', $lead))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $lead->fresh();
        $this->assertSame('new', $fresh->status?->value ?? $fresh->status);
        $this->assertFalse((bool) $fresh->notifications_suppressed);
        $this->assertNotNull($fresh->restored_at);
    }
}
