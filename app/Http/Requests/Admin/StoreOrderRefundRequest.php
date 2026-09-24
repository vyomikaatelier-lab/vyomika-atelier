<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use App\Models\OrderRefund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRefundRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{10,64}$/'],
            'kind' => ['required', 'in:full,partial'],
            'reason_code' => ['required', 'in:'.implode(',', OrderRefund::REASONS)],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'current_password' => ['required', 'current_password'],
            'order_number_confirmation' => [
                Rule::requiredIf(fn () => $this->input('kind') === 'full'),
                'nullable',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('kind') !== 'full') {
                        return;
                    }

                    $order = $this->route('order');
                    $expected = $order instanceof Order ? (string) $order->order_number : '';
                    $typed = is_string($value) ? $value : '';

                    if ($expected === '' || ! hash_equals($expected, $typed)) {
                        $fail('Enter the order number exactly to confirm this refund.');
                    }
                },
            ],
            'include_shipping' => ['nullable', 'boolean'],
            'lines' => ['nullable', 'array'],
            'lines.*' => ['integer', 'min:0', 'max:10000'],
        ];
    }
}
