<?php

namespace Tests\Unit;

use App\Exceptions\WhatsAppDeliveryException;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Services\WhatsApp\MetaWhatsAppProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWhatsAppProviderTest extends TestCase
{
    public function test_send_otp_posts_auth_template_with_body_and_button(): void
    {
        config([
            'whatsapp.meta' => [
                'access_token' => 'test-token',
                'phone_number_id' => '1234567890',
                'otp_template_name' => 'vyomika_otp',
                'otp_template_language' => 'en_US',
                'api_version' => 'v21.0',
            ],
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
        ]);

        (new MetaWhatsAppProvider)->sendOtp('+919876543210', '123456');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'graph.facebook.com/v21.0/1234567890/messages')
                && $body['to'] === '919876543210'
                && $body['template']['name'] === 'vyomika_otp'
                && $body['template']['language']['code'] === 'en_US'
                && $body['template']['components'][0]['type'] === 'body'
                && $body['template']['components'][0]['parameters'][0]['text'] === '123456'
                && $body['template']['components'][1]['type'] === 'button'
                && $body['template']['components'][1]['sub_type'] === 'url'
                && $body['template']['components'][1]['parameters'][0]['text'] === '123456';
        });
    }

    public function test_send_otp_throws_when_not_configured(): void
    {
        config([
            'whatsapp.meta' => [
                'access_token' => null,
                'phone_number_id' => null,
                'otp_template_name' => null,
                'api_version' => 'v21.0',
            ],
        ]);

        $this->expectException(WhatsAppNotConfiguredException::class);

        (new MetaWhatsAppProvider)->sendOtp('919876543210', '123456');
    }

    public function test_send_otp_throws_on_api_failure(): void
    {
        config([
            'whatsapp.meta' => [
                'access_token' => 'test-token',
                'phone_number_id' => '1234567890',
                'otp_template_name' => 'vyomika_otp',
                'otp_template_language' => 'en_US',
                'api_version' => 'v21.0',
            ],
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not found']], 400),
        ]);

        $this->expectException(WhatsAppDeliveryException::class);

        (new MetaWhatsAppProvider)->sendOtp('919876543210', '123456');
    }
}
