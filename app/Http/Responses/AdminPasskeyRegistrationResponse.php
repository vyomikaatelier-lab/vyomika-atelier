<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse as PasskeyRegistrationResponseContract;
use Laravel\Passkeys\Passkey;
use Symfony\Component\HttpFoundation\Response;

class AdminPasskeyRegistrationResponse implements PasskeyRegistrationResponseContract
{
    protected ?Passkey $passkey = null;

    public function withPasskey(Passkey $passkey): static
    {
        $this->passkey = $passkey;

        return $this;
    }

    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            $data = ['status' => 'passkey-registered'];

            if ($this->passkey instanceof Passkey) {
                $data['id'] = (string) $this->passkey->id;
                $data['name'] = $this->passkey->name;
            }

            return new JsonResponse($data, 200);
        }

        return redirect()
            ->route('admin.passkeys.manage')
            ->with('success', 'Passkey registered successfully.');
    }
}
