<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function leadWithPrivateAttachment(): Lead
    {
        Storage::fake('local');
        Storage::disk('local')->put('lead-uploads/drawing.pdf', UploadedFile::fake()->create('drawing.pdf', 10)->getContent());

        return Lead::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '9999999999',
            'type' => 'custom_order',
            'message' => 'Please quote this.',
            'status' => 'new',
            'metadata' => [
                'drawing_path' => 'lead-uploads/drawing.pdf',
                'drawing_filename' => 'drawing.pdf',
            ],
        ]);
    }

    public function test_guest_cannot_download_private_lead_attachment(): void
    {
        $lead = $this->leadWithPrivateAttachment();

        $response = $this->get(route('admin.leads.attachment', $lead));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_non_admin_cannot_download_private_lead_attachment(): void
    {
        $lead = $this->leadWithPrivateAttachment();
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('admin.leads.attachment', $lead));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_download_private_lead_attachment(): void
    {
        $lead = $this->leadWithPrivateAttachment();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAsAdmin($admin)->get(route('admin.leads.attachment', $lead));

        $response->assertOk();
    }
}
