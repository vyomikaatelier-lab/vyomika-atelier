<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RetryOrderRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminPermission(\App\Support\AdminRole::ORDERS_REFUND) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
        ];
    }
}
