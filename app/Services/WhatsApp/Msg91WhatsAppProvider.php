<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Exceptions\WhatsAppDeliveryException;
use App\Exceptions\WhatsAppNotConfiguredException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Msg91WhatsAppProvider implements WhatsAppProvider
{
    private const API_URL = 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';

    public function isConfigured(): bool
    {
        $config = config('whatsapp.msg91');

        return filled($config['auth_key'] ?? null)
            && filled($config['integrated_number'] ?? null)
            && filled($config['template_name'] ?? null)
            && filled($config['template_namespace'] ?? null);
    }

    public function sendOtp(string $mobileE164, string $otp): void
    {
        if (! $this->isConfigured()) {
            throw new WhatsAppNotConfiguredException;
        }

        $config = config('whatsapp.msg91');

        $payload = [
            'integrated_number' => $config['integrated_number'],
            'content_type' => 'template',
            'payload' => [
                'messaging_product' => 'whatsapp',
                'type' => 'template',
                'template' => [
                    'name' => $config['template_name'],
                    'language' => [
                        'code' => $config['template_language'],
                        'policy' => 'deterministic',
                    ],
                    'namespace' => $config['template_namespace'],
                    'to_and_components' => [
                        [
                            'to' => [$mobileE164],
                            'components' => [
                                'body_1' => [
                                    'type' => 'text',
                                    'value' => $otp,
                                ],
                                'button_1' => [
                                    'subtype' => 'url',
                                    'type' => 'text',
                                    'value' => $otp,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders(['authkey' => $config['auth_key']])
            ->timeout(15)
            ->post(self::API_URL, $payload);

        if (! $response->successful()) {
            Log::warning('WhatsApp OTP delivery failed', [
                'mobile_e164' => $mobileE164,
                'status' => $response->status(),
                'error' => $response->json('message') ?? $response->json('errors') ?? $response->body(),
            ]);

            throw new WhatsAppDeliveryException('Unable to deliver WhatsApp verification message.');
        }

        Log::info('WhatsApp OTP sent', [
            'mobile_e164' => $mobileE164,
            'provider' => 'msg91',
            'response' => $response->json('request_id') ?? $response->json('type'),
        ]);
    }
}
