<?php

namespace Tests\Feature;

use App\Models\BlockedIdentity;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadProtectionService;
use App\Services\TurnstileService;
use App\Support\LeadProtectionStatus;
use App\Support\LeadStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'form_protection.turnstile.skip_verification' => false,
            'form_protection.turnstile.testing_bypass_token' => 'test-turnstile-pass',
            'services.admin_email' => 'admin@vyomikaatelier.com',
        ]);
    }

    /** @return array<string, mixed> */
    private function protectionFields(string $formKey, int $loadedSecondsAgo = 10): array
    {
        $turnstile = app(TurnstileService::class);

        return [
            'form_loaded_at' => Crypt::encryptString(json_encode([
                'form' => $formKey,
                'loaded_at' => now()->subSeconds($loadedSecondsAgo)->timestamp,
            ])),
            'turnstile_fallback_token' => $turnstile->fallbackToken($formKey),
            'turnstile_unavailable' => '0',
            'cf-turnstile-response' => 'test-turnstile-pass',
            'enquiry_intent' => 'active_project',
        ];
    }

    /** @return array<string, mixed> */
    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
            'phone' => '9876543210',
            'subject' => 'Partition enquiry',
            'message' => 'We are renovating a Mumbai apartment and need PVD partitions for the living area with custom dimensions and timeline by August.',
        ], $this->protectionFields('contact'), $overrides);
    }

    public function test_genuine_contact_enquiry_is_accepted(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), $this->contactPayload());

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('leads', [
            'email' => 'priya@example.com',
            'type' => 'contact',
            'enquiry_type' => 'general',
        ]);

        $lead = Lead::where('email', 'priya@example.com')->first();
        $this->assertNotNull($lead);
        $this->assertGreaterThan(0, $lead->lead_score);
    }

    public function test_honeypot_submission_is_rejected(): void
    {
        $payload = $this->contactPayload([
            config('form_protection.honeypot_field') => 'http://spam.test',
        ]);

        $this->post(route('contact.store'), $payload)->assertSessionHasErrors('form');
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_too_fast_submission_is_rejected(): void
    {
        $payload = $this->contactPayload();
        $payload['form_loaded_at'] = Crypt::encryptString(json_encode([
            'form' => 'contact',
            'loaded_at' => now()->timestamp,
        ]));

        $this->post(route('contact.store'), $payload)->assertSessionHasErrors('form');
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_invalid_turnstile_token_is_rejected(): void
    {
        $payload = $this->contactPayload(['cf-turnstile-response' => 'invalid-token']);
        $this->post(route('contact.store'), $payload)->assertSessionHasErrors('form');
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_rate_limit_returns_friendly_429(): void
    {
        $payload = $this->contactPayload();

        for ($i = 0; $i < 3; $i++) {
            $payload['email'] = "user{$i}@example.com";
            $this->post(route('contact.store'), $payload)->assertSessionHas('success');
        }

        $this->post(route('contact.store'), $payload)->assertStatus(429);
    }

    public function test_duplicate_enquiry_is_flagged_and_notification_suppressed(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->contactPayload())->assertSessionHas('success');
        $this->post(route('contact.store'), $this->contactPayload(['name' => 'Another Name']))->assertSessionHas('success');

        $this->assertDatabaseHas('leads', [
            'email' => 'priya@example.com',
            'protection_status' => LeadProtectionStatus::DUPLICATE,
            'notifications_suppressed' => true,
        ]);

        $original = Lead::where('email', 'priya@example.com')->whereNull('duplicate_of_id')->first();
        $this->assertNotNull($original);
        $this->assertGreaterThanOrEqual(1, $original->duplicate_count);
        Mail::assertSentCount(1);
    }

    public function test_vendor_pitch_routed_to_marketing_vendor_queue(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->contactPayload([
            'enquiry_intent' => 'vendor_proposal',
            'message' => 'We offer SEO and digital marketing services for luxury brands.',
        ]))->assertSessionHas('success');

        $this->assertDatabaseHas('leads', [
            'email' => 'priya@example.com',
            'protection_status' => LeadProtectionStatus::MARKETING_VENDOR,
            'enquiry_type' => 'vendor_marketing',
        ]);
    }

    public function test_vendor_pitch_notifies_marketing_email_when_configured(): void
    {
        config([
            'services.marketing_email' => 'marketing@vyomikaatelier.com',
            'services.admin_email' => 'admin@vyomikaatelier.com',
        ]);

        $lead = new Lead([
            'protection_status' => LeadProtectionStatus::MARKETING_VENDOR,
            'enquiry_type' => 'vendor_marketing',
            'lead_score' => 40,
            'notifications_suppressed' => false,
        ]);

        $this->assertSame('marketing@vyomikaatelier.com', app(LeadProtectionService::class)->recipientFor($lead));
    }

    public function test_form_protection_token_endpoint_returns_fresh_token(): void
    {
        $response = $this->getJson(route('form-protection.token', ['formKey' => 'contact']));
        $response->assertOk()->assertJsonStructure(['form_loaded_at']);
    }

    public function test_suspicious_lead_retained_for_admin_review(): void
    {
        $this->post(route('contact.store'), $this->contactPayload([
            'email' => 'pitch@mailinator.com',
            'message' => 'Buy our SEO services now https://spam1.test https://spam2.test https://spam3.test https://spam4.test',
        ]))->assertSessionHas('success');

        $lead = Lead::where('email', 'pitch@mailinator.com')->first();
        $this->assertNotNull($lead);
        $this->assertContains($lead->protection_status, [
            LeadProtectionStatus::SPAM_SUSPECTED,
            LeadProtectionStatus::NEEDS_VERIFICATION,
        ]);
    }

    public function test_disposable_email_reduces_score(): void
    {
        $this->post(route('contact.store'), $this->contactPayload([
            'email' => 'test@mailinator.com',
        ]))->assertSessionHas('success');

        $this->assertContains('disposable_email', Lead::where('email', 'test@mailinator.com')->first()->lead_score_reasons ?? []);
    }

    public function test_hot_lead_score_with_rich_enquiry(): void
    {
        Mail::fake();
        $turnstile = app(TurnstileService::class);

        $payload = array_merge($this->protectionFields('custom_order'), [
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
            'phone' => '9876543210',
            'type' => 'custom_order',
            'message' => 'We need PVD partitions for a 2400mm x 2800mm opening in Bandra West. Budget around 8-12 lakhs. Need installation by September. Architect: Studio Form.',
            'project_location' => 'Bandra West, Mumbai',
            'dimensions' => '2400 x 2800 mm',
            'budget' => '8-12 lakhs',
            'timeline' => 'September 2026',
            'enquiry_intent' => 'active_project',
        ]);

        $this->withSession([
            'va_attribution' => [
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'first_touch_source' => 'google / cpc',
                'last_touch_source' => 'google / cpc',
                'landing_page' => 'https://vyomikaatelier.com/custom-order',
                'device_type' => 'desktop',
            ],
        ])->post(route('leads.store'), $payload)->assertSessionHas('success');

        $lead = Lead::where('email', 'priya@example.com')->first();
        $this->assertNotNull($lead);
        $this->assertGreaterThanOrEqual(70, $lead->lead_score);
        $this->assertSame('hot', $lead->priority);
        $this->assertSame('google', $lead->utm_source);
    }

    public function test_admin_false_positive_marks_verified(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = Lead::create([
            'name' => 'Test',
            'email' => 'spam@test.com',
            'type' => 'contact',
            'message' => 'test',
            'status' => LeadStatus::SPAM_SUSPECTED,
            'protection_status' => LeadProtectionStatus::SPAM_SUSPECTED,
            'lead_score' => 5,
        ]);

        $this->actingAs($admin)->post(route('admin.leads.false-positive', $lead));
        $lead->refresh();

        $this->assertSame(LeadProtectionStatus::VERIFIED, $lead->protection_status);
        $this->assertSame(LeadStatus::VERIFIED, $lead->status);
    }

    public function test_blocked_identity_rejects_submission(): void
    {
        BlockedIdentity::create([
            'identity_type' => 'email',
            'value_hash' => BlockedIdentity::hashValue('blocked@example.com'),
            'value_hint' => 'bl****@example.com',
            'is_active' => true,
        ]);

        $this->post(route('contact.store'), $this->contactPayload(['email' => 'blocked@example.com']))->assertSessionHas('success');
        $this->assertSame(LeadProtectionStatus::BLOCKED, Lead::where('email', 'blocked@example.com')->first()->protection_status);
    }

    public function test_expired_block_allows_submission(): void
    {
        BlockedIdentity::create([
            'identity_type' => 'email',
            'value_hash' => BlockedIdentity::hashValue('expired@example.com'),
            'value_hint' => 'ex****@example.com',
            'is_active' => true,
            'expires_at' => now()->subHour(),
        ]);

        $this->post(route('contact.store'), $this->contactPayload(['email' => 'expired@example.com']))->assertSessionHas('success');
        $this->assertNotSame(LeadProtectionStatus::BLOCKED, Lead::where('email', 'expired@example.com')->first()->protection_status);
    }

    public function test_private_upload_requires_admin_auth(): void
    {
        Storage::fake('local');
        $path = 'lead-uploads/test.pdf';
        Storage::disk('local')->put($path, 'test');

        $lead = Lead::create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'type' => 'custom_order',
            'message' => 'test',
            'metadata' => ['drawing_path' => $path],
        ]);

        $this->get(route('admin.leads.attachment', $lead))->assertRedirect();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.leads.attachment', $lead))->assertOk();
    }

    public function test_notification_failure_does_not_lose_lead(): void
    {
        $this->post(route('contact.store'), $this->contactPayload(['email' => 'notify-fail@example.com']))->assertSessionHas('success');
        $this->assertDatabaseHas('leads', ['email' => 'notify-fail@example.com']);

        Mail::shouldReceive('raw')->once()->andThrow(new \Exception('SMTP down'));
        $lead = Lead::where('email', 'notify-fail@example.com')->first();
        $this->assertFalse(app(LeadProtectionService::class)->notifyAdmin($lead, 'details', 'subject'));
    }

    public function test_existing_records_remain_readable(): void
    {
        $lead = Lead::create([
            'name' => 'Legacy',
            'email' => 'legacy@example.com',
            'type' => 'contact',
            'message' => 'Old lead message',
            'status' => 'contacted',
            'protection_status' => LeadProtectionStatus::NEEDS_VERIFICATION,
            'risk_score' => 45,
        ]);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.leads.show', $lead))->assertOk();
    }

    public function test_vendor_form_excluded_from_sales_queue(): void
    {
        Mail::fake();
        $turnstile = app(TurnstileService::class);

        $this->post(route('vendor-proposal.store'), [
            'name' => 'Agency',
            'email' => 'agency@vendor.com',
            'company' => 'SEO Co',
            'message' => 'We provide digital marketing services for luxury brands worldwide.',
            'form_loaded_at' => Crypt::encryptString(json_encode(['form' => 'vendor_proposal', 'loaded_at' => now()->subSeconds(10)->timestamp])),
            'turnstile_fallback_token' => $turnstile->fallbackToken('vendor_proposal'),
            'turnstile_unavailable' => '0',
            'cf-turnstile-response' => 'test-turnstile-pass',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('leads', [
            'email' => 'agency@vendor.com',
            'enquiry_type' => 'vendor_marketing',
        ]);
    }
}
