<?php

namespace Tests\Unit;

use App\Exceptions\WhatsAppDeliveryException;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Services\WhatsApp\Msg91WhatsAppProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Msg91WhatsAppProviderTest extends TestCase
{
    public function test_is_configured_requires_auth_key_integrated_number_template_name_and_namespace(): void
    {
        config([
            'whatsapp.msg91' => [
                'auth_key' => '',
                'integrated_number' => '919999999999',
                'template_name' => 'vyomika_otp',
                'template_namespace' => 'vyomika_namespace',
                'template_language' => 'en',
            ],
        ]);

        $this->assertFalse((new Msg91WhatsAppProvider)->isConfigured());
    }

    public function test_send_otp_posts_msg91_bulk_template_payload(): void
    {
        config([
            'whatsapp.msg91' => [
                'auth_key' => 'test-auth-key',
                'integrated_number' => '919999999999',
                'template_name' => 'vyomika_otp',
                'template_namespace' => 'vyomika_namespace',
                'template_language' => 'en',
            ],
        ]);

        Http::fake([
            'api.msg91.com/*' => Http::response(['type' => 'success', 'request_id' => 'req-123'], 200),
        ]);

        (new Msg91WhatsAppProvider)->sendOtp('919876543210', '123456');

        Http::assertSent(function ($request) {
            $body = $request->data();
            $components = $body['payload']['template']['to_and_components'][0]['components'];

            return $request->url() === 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/'
                && $request->hasHeader('authkey', 'test-auth-key')
                && $body['integrated_number'] === '919999999999'
                && $body['content_type'] === 'template'
                && $body['payload']['template']['name'] === 'vyomika_otp'
                && $body['payload']['template']['namespace'] === 'vyomika_namespace'
                && $body['payload']['template']['language'] === ['code' => 'en', 'policy' => 'deterministic']
                && $body['payload']['template']['to_and_components'][0]['to'] === ['919876543210']
                && $components['body_1'] === ['type' => 'text', 'value' => '123456']
                && $components['button_1'] === ['subtype' => 'url', 'type' => 'text', 'value' => '123456'];
        });
    }

    public function test_send_otp_throws_when_not_configured(): void
    {
        config([
            'whatsapp.msg91' => [
                'auth_key' => null,
                'integrated_number' => null,
                'template_name' => null,
                'template_namespace' => null,
                'template_language' => 'en',
            ],
        ]);

        $this->expectException(WhatsAppNotConfiguredException::class);

        (new Msg91WhatsAppProvider)->sendOtp('919876543210', '123456');
    }

    public function test_send_otp_throws_on_api_failure(): void
    {
        config([
            'whatsapp.msg91' => [
                'auth_key' => 'test-auth-key',
                'integrated_number' => '919999999999',
                'template_name' => 'vyomika_otp',
                'template_namespace' => 'vyomika_namespace',
                'template_language' => 'en',
            ],
        ]);

        Http::fake([
            'api.msg91.com/*' => Http::response(['message' => 'Invalid template'], 400),
        ]);

        $this->expectException(WhatsAppDeliveryException::class);

        (new Msg91WhatsAppProvider)->sendOtp('919876543210', '123456');
    }
}
