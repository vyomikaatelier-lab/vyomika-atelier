<?php

namespace App\Http\Controllers;

use App\Services\FormProtectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormProtectionController extends Controller
{
    public function __construct(
        private FormProtectionService $protection,
    ) {}

    public function token(Request $request, string $formKey): JsonResponse
    {
        $allowed = array_keys(config('form_protection.form_groups', []));
        abort_unless(in_array($formKey, $allowed, true), 404);

        return response()->json([
            'form_loaded_at' => $this->protection->formLoadedToken($formKey),
        ]);
    }
}
