<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Support\CmsSettings;
use App\Support\LeadStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clearing a field in the admin panel must persist as "empty" instead of being
 * discarded (which looks like the save silently ignored the change).
 */
class AdminClearFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_follow_up_and_assignee_can_be_cleared(): void
    {
        $admin = User::factory()->admin()->create();
        $assignee = User::factory()->admin()->create();

        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
            'type' => 'contact',
            'message' => 'Hello',
            'status' => LeadStatus::NEW,
            'assigned_to' => $assignee->id,
            'next_follow_up_at' => now()->addDays(3),
            'expected_order_value' => 50000,
            'lost_reason' => 'Budget',
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.leads.update', $lead), [
            'status' => LeadStatus::NEW,
            'assigned_to' => '',
            'next_follow_up_at' => '',
            'expected_order_value' => '',
            'lost_reason' => '',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertNull($lead->assigned_to);
        $this->assertNull($lead->next_follow_up_at);
        $this->assertNull($lead->expected_order_value);
        $this->assertNull($lead->lost_reason);
    }

    public function test_lead_fields_absent_from_the_form_keep_their_value(): void
    {
        $admin = User::factory()->admin()->create();
        $assignee = User::factory()->admin()->create();

        $lead = Lead::create([
            'name' => 'Test Lead',
            'email' => 'lead@example.com',
            'type' => 'contact',
            'message' => 'Hello',
            'status' => LeadStatus::NEW,
            'assigned_to' => $assignee->id,
            'admin_notes' => 'Keep me',
        ]);

        $this->actingAsAdmin($admin)->put(route('admin.leads.update', $lead), [
            'status' => LeadStatus::QUALIFIED,
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame(LeadStatus::QUALIFIED, $lead->status);
        $this->assertSame($assignee->id, $lead->assigned_to);
        $this->assertSame('Keep me', $lead->admin_notes);
    }

    public function test_clearing_announcement_hides_it_on_the_storefront(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->put(route('admin.settings.update'), [
            'current_password' => 'password',
            'brand_name' => 'Vyomika Atelier',
            'announcement_text' => 'Festive Offer',
            'announcement_link_label' => 'Shop Now',
            'announcement_link_href' => '/shop',
        ])->assertRedirect();

        CmsSettings::hydrate();
        $this->assertSame('Festive Offer', config('site.announcement.text'));

        $this->actingAsAdmin($admin)->put(route('admin.settings.update'), [
            'current_password' => 'password',
            'brand_name' => 'Vyomika Atelier',
            'announcement_text' => '',
            'announcement_link_label' => '',
            'announcement_link_href' => '',
        ])->assertRedirect();

        CmsSettings::hydrate();
        $this->assertNull(config('site.announcement.text'));

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Festive Offer');
    }
}
