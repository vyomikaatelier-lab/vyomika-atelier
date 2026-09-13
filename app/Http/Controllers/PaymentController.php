<?php

namespace App\Http\Controllers;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use App\Support\OrderAccess;
use App\Support\StorefrontRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private RazorpayService $razorpay,
        private OrderPaymentService $payments,
    ) {}

    public function show(Order $order)
    {
        if (! OrderAccess::canAccess($order)) {
            return redirect(StorefrontRoutes::primaryShopUrl())->with('error', 'Order not found.');
        }

        if ($order->isFulfilled()) {
            return redirect()->route('checkout.success', $order);
        }

        if ($order->isCancelled()) {
            return view('checkout.payment-cancelled', ['order' => $order]);
        }

        if ($order->isExpired()) {
            return view('checkout.payment-expired', ['order' => $order]);
        }

        if ($order->status !== 'pending' || $order->payment_method !== 'razorpay') {
            return redirect(StorefrontRoutes::primaryShopUrl())
                ->with('error', 'This order is not awaiting payment.');
        }

        if (! $this->razorpay->isConfigured()) {
            return redirect()->route('checkout.index')
                ->with('error', config('addresses.payment_unavailable_message'));
        }

        return view('checkout.pay', [
            'order' => $order,
            'razorpayKey' => $this->razorpay->key(),
        ]);
    }

    public function verify(Request $request, Order $order)
    {
        $sessionAuthorised = OrderAccess::canAccess($order);

        if (! $sessionAuthorised && ! $this->callbackSignatureMatchesOrder($request, $order)) {
            return redirect(StorefrontRoutes::primaryShopUrl())->with('error', 'Order not found.');
        }

        if ($order->isFulfilled()) {
            return $this->afterPaymentRecorded($order, $sessionAuthorised);
        }

        if ($order->isCancelled()) {
            return view('checkout.payment-cancelled', ['order' => $order->fresh()]);
        }

        if ($order->isExpired()) {
            return view('checkout.payment-expired', ['order' => $order->fresh()]);
        }

        if ($order->status !== 'pending') {
            return redirect(StorefrontRoutes::primaryShopUrl())
                ->with('error', 'This order is not awaiting payment.');
        }

        $validated = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        try {
            $this->payments->verifyAndComplete(
                $order,
                $validated['razorpay_payment_id'],
                $validated['razorpay_order_id'],
                $validated['razorpay_signature'],
            );
        } catch (RazorpayReconciliationRequiredException $e) {
            return redirect(StorefrontRoutes::primaryShopUrl())
                ->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            if ($order->fresh()?->isExpired()) {
                return view('checkout.payment-expired', ['order' => $order->fresh()]);
            }

            return redirect()->route('checkout.pay', $order)
                ->with('error', $e->getMessage());
        }

        return $this->afterPaymentRecorded($order->fresh(), $sessionAuthorised);
    }

    /**
     * The gateway redirect callback reaches us without the storefront session,
     * so ownership cannot be proven by cookie. A signature over this order's
     * own stored Razorpay order ID is proof the gateway sent the request, and
     * it cannot be replayed against a different local order.
     */
    private function callbackSignatureMatchesOrder(Request $request, Order $order): bool
    {
        if (! $this->razorpay->isConfigured()) {
            return false;
        }

        $storedOrderId = (string) $order->razorpay_order_id;

        if ($storedOrderId === '') {
            return false;
        }

        $submittedOrderId = $this->stringInput($request, 'razorpay_order_id');
        $paymentId = $this->stringInput($request, 'razorpay_payment_id');
        $signature = $this->stringInput($request, 'razorpay_signature');

        if ($paymentId === '' || $signature === '') {
            return false;
        }

        if (! hash_equals($storedOrderId, $submittedOrderId)) {
            return false;
        }

        return $this->razorpay->verifySignature($storedOrderId, $paymentId, $signature);
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }

    /**
     * A signature-authorised callback has no session to carry, so the customer
     * signs in again to reach the confirmation. The payment is already recorded.
     */
    private function afterPaymentRecorded(Order $order, bool $sessionAuthorised): RedirectResponse
    {
        if ($sessionAuthorised) {
            return redirect()->route('checkout.success', $order);
        }

        return redirect()->route('account.login')->with(
            'info',
            'Payment received for order #'.$order->order_number.'. Please sign in to view your confirmation.'
        );
    }
}
