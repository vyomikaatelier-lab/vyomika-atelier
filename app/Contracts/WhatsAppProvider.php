<?php

namespace App\Contracts;

interface WhatsAppProvider
{
    /**
     * Send a one-time password to the given E.164 number (digits only, no +).
     *
     * @throws \App\Exceptions\WhatsAppNotConfiguredException
     * @throws \App\Exceptions\WhatsAppDeliveryException
     */
    public function sendOtp(string $mobileE164, string $otp): void;

    public function isConfigured(): bool;
}
