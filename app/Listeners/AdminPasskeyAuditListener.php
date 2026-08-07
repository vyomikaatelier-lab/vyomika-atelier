<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;

class AdminPasskeyAuditListener
{
    public function registered(PasskeyRegistered $event): void
    {
        Log::info('admin.passkey_registered', [
            'user_id' => $event->user->getKey(),
            'passkey_id' => $event->passkey->id,
            'passkey_name' => $event->passkey->name,
        ]);
    }

    public function verified(PasskeyVerified $event): void
    {
        Log::info('admin.passkey_used', [
            'user_id' => $event->user->getKey(),
            'passkey_id' => $event->passkey->id,
            'passkey_name' => $event->passkey->name,
        ]);
    }

    public function deleted(PasskeyDeleted $event): void
    {
        Log::info('admin.passkey_removed', [
            'user_id' => $event->user->getKey(),
            'passkey_id' => $event->passkey->id,
            'passkey_name' => $event->passkey->name,
        ]);
    }
}
