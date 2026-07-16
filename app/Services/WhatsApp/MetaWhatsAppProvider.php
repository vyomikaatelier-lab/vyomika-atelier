<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Exceptions\WhatsAppDeliveryException;
use App\Exceptions\WhatsAppNotConfiguredException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppProvider implements WhatsAppProvider
{
    public function isConfigured(): bool
    {
        $config = config('whatsapp.meta');

        return filled($config['access_token'] ?? null)
            && filled($config['phone_number_id'] ?? null)
            && filled($config['otp_template_name'] ?? null);
    }

    public function sendOtp(string $mobileE164, string $otp): void
    {
        if (! $this->isConfigured()) {
            throw new WhatsAppNotConfiguredException;
        }

        $config = config('whatsapp.meta');
        $version = $config['api_version'];
        $phoneId = $config['phone_number_id'];
        $url = "https://graph.facebook.com/{$version}/{$phoneId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $mobileE164,
            'type' => 'template',
            'template' => [
                'name' => $config['otp_template_name'],
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $otp],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($config['access_token'])
            ->timeout(15)
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::warning('WhatsApp OTP delivery failed', [
                'mobile_e164' => $mobileE164,
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? $response->body(),
            ]);

            throw new WhatsAppDeliveryException('Unable to deliver WhatsApp verification message.');
        }

        Log::info('WhatsApp OTP sent', [
            'mobile_e164' => $mobileE164,
            'message_id' => $response->json('messages.0.id'),
        ]);
    }
}
