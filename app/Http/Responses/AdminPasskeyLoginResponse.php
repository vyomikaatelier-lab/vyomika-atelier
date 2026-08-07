<?php

namespace App\Http\Responses;

use App\Support\AdminAuthFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class AdminPasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function __construct(private readonly AdminAuthFlow $authFlow) {}

    public function toResponse($request): Response
    {
        /** @var Request $request */
        $redirect = $this->authFlow->completeAdminLogin($request, $request->user(), 'passkey');

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => $redirect->getTargetUrl(),
            ], 200);
        }

        return $redirect;
    }
}
