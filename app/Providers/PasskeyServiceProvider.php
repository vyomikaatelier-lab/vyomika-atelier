<?php

namespace App\Providers;

use App\Http\Responses\AdminPasskeyDeletedResponse;
use App\Http\Responses\AdminPasskeyLoginResponse;
use App\Http\Responses\AdminPasskeyRegistrationResponse;
use App\Listeners\AdminPasskeyAuditListener;
use App\Passkeys\GenerateAdminRegistrationOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;

class PasskeyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PasskeyLoginResponse::class, AdminPasskeyLoginResponse::class);
        $this->app->singleton(PasskeyRegistrationResponse::class, AdminPasskeyRegistrationResponse::class);
        $this->app->singleton(PasskeyDeletedResponse::class, AdminPasskeyDeletedResponse::class);
        $this->app->bind(GenerateRegistrationOptions::class, GenerateAdminRegistrationOptions::class);
    }

    public function boot(): void
    {
        Passkeys::ignoreRoutes();

        Passkeys::authorizeLoginUsing(function (Request $request, PasskeyUser $user, Passkey $passkey): bool {
            if (! $user->isAdmin() || ! $user->is_active) {
                Log::warning('admin.passkey_login_failed', [
                    'user_id' => $user->getKey(),
                    'passkey_id' => $passkey->id,
                    'ip' => $request->ip(),
                    'reason' => 'not_admin_or_inactive',
                ]);

                throw InvalidPasskeyException::make('Unable to sign in with this passkey.');
            }

            return true;
        });

        Event::listen(PasskeyRegistered::class, [AdminPasskeyAuditListener::class, 'registered']);
        Event::listen(PasskeyVerified::class, [AdminPasskeyAuditListener::class, 'verified']);
        Event::listen(PasskeyDeleted::class, [AdminPasskeyAuditListener::class, 'deleted']);
    }

    /**
     * Reset cached WebAuthn serializers between tests.
     */
    public static function flushWebAuthn(): void
    {
        WebAuthn::flush();
    }
}
