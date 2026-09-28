<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use App\Support\AdminRole;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmTestOrderArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAdminPermission(AdminRole::ORDERS_ARCHIVE_TEST) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'order_number_confirmation' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $order = $this->route('order');
                    $expected = $order instanceof Order ? (string) $order->order_number : '';
                    $typed = is_string($value) ? $value : '';

                    if ($expected === '' || ! hash_equals($expected, $typed)) {
                        $fail($this->confirmationMessage());
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'The password is incorrect.',
        ];
    }

    private function confirmationMessage(): string
    {
        return $this->routeIs('admin.orders.test-archive.destroy')
            ? 'Enter the order number exactly to confirm unarchiving.'
            : 'Enter the order number exactly to confirm archiving.';
    }
}
