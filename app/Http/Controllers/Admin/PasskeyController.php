<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminMfa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;

class PasskeyController extends Controller
{
    private const MANAGEMENT_SESSION_KEY = 'admin.passkey.management_verified_at';

    private const MANAGEMENT_TTL_SECONDS = 300;

    public function __construct(private readonly AdminMfa $mfa) {}

    public function showManage(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $passkeys = $user->passkeys()->orderByDesc('last_used_at')->orderByDesc('created_at')->get();

        return view('admin.auth.passkeys-manage', [
            'passkeys' => $passkeys,
            'mfaEnabled' => $this->mfa->hasMfaEnabled($user),
        ]);
    }

    public function registrationOptions(Request $request, GenerateRegistrationOptions $generate): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->verifyManagementCredentials($request, $user);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $options = $generate($user);

        $request->session()->put('passkey.registration_options', WebAuthn::toJson($options));
        $request->session()->put('admin.passkey.pending_name', $request->string('name')->toString());

        return response()->json([
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    public function store(
        PasskeyRegistrationRequest $request,
        StorePasskey $storePasskey,
    ): PasskeyRegistrationResponse {
        /** @var User $user */
        $user = $request->user();

        $this->ensureManagementVerified($request);

        $name = $request->session()->pull('admin.passkey.pending_name', $request->string('name')->toString());

        $passkey = $storePasskey(
            $user,
            $name,
            $request->credential(),
            $request->registrationOptions()
        );

        $request->session()->forget(self::MANAGEMENT_SESSION_KEY);

        return app(PasskeyRegistrationResponse::class)->withPasskey($passkey);
    }

    public function update(Request $request, Passkey $passkey): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless((string) $passkey->user_id === (string) $user->getKey(), 403);

        $this->verifyManagementCredentials($request, $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $passkey->forceFill(['name' => $validated['name']])->save();

        Log::info('admin.passkey_renamed', [
            'user_id' => $user->id,
            'passkey_id' => $passkey->id,
            'passkey_name' => $passkey->name,
        ]);

        return response()->json([
            'status' => 'passkey-renamed',
            'id' => (string) $passkey->id,
            'name' => $passkey->name,
        ]);
    }

    public function destroy(
        Request $request,
        Passkey $passkey,
        DeletePasskey $deletePasskey,
    ): PasskeyDeletedResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless((string) $passkey->user_id === (string) $user->getKey(), 403);

        $this->verifyManagementCredentials($request, $user);

        if (! $this->canRemovePasskey($user, $passkey)) {
            throw ValidationException::withMessages([
                'passkey' => 'Keep at least one fallback sign-in method. Enroll two-factor authentication before removing your only passkey.',
            ]);
        }

        $deletePasskey($user, $passkey);

        $request->session()->forget(self::MANAGEMENT_SESSION_KEY);

        return app(PasskeyDeletedResponse::class);
    }

    private function verifyManagementCredentials(Request $request, User $user): void
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'totp_code' => ['required', 'string', 'max:64'],
        ]);

        if (! $this->mfa->hasMfaEnabled($user)) {
            throw ValidationException::withMessages([
                'totp_code' => 'Enroll two-factor authentication before managing passkeys.',
            ]);
        }

        if (! $this->mfa->verifyTotp($user, $request->string('totp_code')->toString())) {
            Log::warning('admin.passkey_management_denied', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'reason' => 'invalid_totp',
            ]);

            throw ValidationException::withMessages([
                'totp_code' => 'Invalid authentication code.',
            ]);
        }

        $request->session()->put(self::MANAGEMENT_SESSION_KEY, now()->timestamp);
    }

    private function ensureManagementVerified(Request $request): void
    {
        $verifiedAt = $request->session()->get(self::MANAGEMENT_SESSION_KEY);

        if (! is_int($verifiedAt) || now()->timestamp - $verifiedAt > self::MANAGEMENT_TTL_SECONDS) {
            throw ValidationException::withMessages([
                'totp_code' => 'Confirm your password and authentication code again.',
            ]);
        }
    }

    private function canRemovePasskey(User $user, Passkey $passkey): bool
    {
        if ($user->passkeys()->count() > 1) {
            return true;
        }

        return $this->mfa->hasMfaEnabled($user);
    }
}
