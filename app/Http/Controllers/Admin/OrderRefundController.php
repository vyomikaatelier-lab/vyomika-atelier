<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RetryOrderRefundRequest;
use App\Http\Requests\Admin\StoreOrderRefundRequest;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Services\OrderRefundService;
use App\Services\RefundIntent;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderRefundController extends Controller
{
    public function store(StoreOrderRefundRequest $request, Order $order, OrderRefundService $refunds): RedirectResponse
    {
        $key = (string) $request->validated('idempotency_key');
        $existing = OrderRefund::query()->where('idempotency_key', $key)->first();

        if ($existing && (int) $existing->order_id !== (int) $order->getKey()) {
            abort(404);
        }

        if (! $existing) {
            $expected = session('order_refund_idempotency.'.$order->getKey());
            if (! is_string($expected) || ! hash_equals($expected, $key)) {
                return back()->withErrors([
                    'idempotency_key' => 'This refund form has expired. Reload the order and try again.',
                ])->withInput();
            }
        }

        $lines = [];
        foreach ((array) $request->validated('lines', []) as $itemId => $quantity) {
            $lines[(int) $itemId] = (int) $quantity;
        }

        $intent = new RefundIntent(
            $key,
            (string) $request->validated('kind'),
            (string) $request->validated('reason_code'),
            $request->validated('internal_note'),
            $request->validated('order_number_confirmation'),
            $request->boolean('include_shipping'),
            $lines,
        );

        try {
            $refund = $existing
                ? $refunds->resume((int) $order->getKey(), (int) $existing->getKey())
                : $refunds->start((int) $order->getKey(), $request->user(), $intent);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (LockTimeoutException) {
            return back()->withErrors([
                'refund' => 'A refund is already being submitted. Please wait a moment.',
            ])->withInput();
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'refund' => $this->safeMessage($exception),
            ])->withInput();
        }

        $this->rememberKey($order, $refund);

        return back()->with('success', $this->successMessage($refund));
    }

    public function retry(RetryOrderRefundRequest $request, Order $order, OrderRefund $refund, OrderRefundService $refunds): RedirectResponse
    {
        if ((int) $refund->order_id !== (int) $order->getKey()) {
            abort(404);
        }

        try {
            $refund = $refunds->resume((int) $order->getKey(), (int) $refund->getKey());
        } catch (LockTimeoutException) {
            return back()->withErrors([
                'refund' => 'A refund is already being submitted. Please wait a moment.',
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'refund' => $this->safeMessage($exception),
            ]);
        }

        $this->rememberKey($order, $refund);

        return back()->with('success', $this->successMessage($refund));
    }

    private function rememberKey(Order $order, OrderRefund $refund): void
    {
        $sessionKey = 'order_refund_idempotency.'.$order->getKey();

        if ($refund->status === OrderRefund::STATUS_SUBMIT_UNCERTAIN) {
            session([$sessionKey => $refund->idempotency_key]);

            return;
        }

        session()->forget($sessionKey);
    }

    private function successMessage(OrderRefund $refund): string
    {
        return match ($refund->status) {
            OrderRefund::STATUS_PROCESSED => 'Refund completed.',
            OrderRefund::STATUS_FAILED => 'This refund was rejected.',
            OrderRefund::STATUS_PENDING => 'Refund submitted and waiting for confirmation.',
            OrderRefund::STATUS_SUBMIT_UNCERTAIN => 'Outcome not yet confirmed. Retrying uses the same idempotency key and cannot change the amount.',
            default => 'The refund could not be confirmed. Review the refund record.',
        };
    }

    private function safeMessage(RuntimeException $exception): string
    {
        $allowed = [
            'This order is awaiting payment reconciliation and cannot be refunded here.',
            'This order cannot be refunded.',
            'The captured amount does not match this order. Refunds are unavailable until the payment is reconciled.',
            'This refund could not be submitted.',
            'Refund request no longer matches the original submission.',
            'Payment confirmation is temporarily unavailable. Please wait a moment.',
            'A refund is already being submitted. Please wait a moment.',
        ];

        $message = $exception->getMessage();

        return in_array($message, $allowed, true)
            ? $message
            : 'The refund could not be confirmed. Do not submit it again. Review the refund record.';
    }
}
