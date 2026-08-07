<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse as PasskeyDeletedResponseContract;
use Symfony\Component\HttpFoundation\Response;

class AdminPasskeyDeletedResponse implements PasskeyDeletedResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        if ($request->wantsJson()) {
            return new JsonResponse(['status' => 'passkey-deleted'], 200);
        }

        return redirect()
            ->route('admin.passkeys.manage')
            ->with('success', 'Passkey removed.');
    }
}
