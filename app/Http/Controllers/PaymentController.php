<?php

namespace App\Http\Controllers;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use App\Support\CheckoutPayments;
use App\Support\OrderAccess;
use App\Support\StorefrontRoutes;
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

        if ($order->needsPaymentReview()) {
            return view('checkout.payment-review', ['order' => $order]);
        }

        if (filled($order->payment_id) || $order->isFulfilled()) {
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

        if (! CheckoutPayments::enabled()) {
            return view('checkout.unavailable');
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

    /**
     * Stateless Razorpay redirect callback.
     *
     * This action runs without session middleware (see routes/web.php), so it
     * must never render a view, flash a message or read authentication state.
     * Every branch returns a redirect to a session-backed GET route, which
     * re-applies the customer's own cookie and enforces order access there.
     */
    public function verify(Request $request, Order $order)
    {
        $paymentId = $this->stringInput($request, 'razorpay_payment_id');
        $submittedOrderId = $this->stringInput($request, 'razorpay_order_id');
        $signature = $this->stringInput($request, 'razorpay_signature');

        // Razorpay signs every redirect callback, so the signature is the only
        // authorisation. An authenticated session is never an alternative.
        if (! $this->callbackIsAuthentic($order, $submittedOrderId, $paymentId, $signature)) {
            return redirect()->route('checkout.pay', $order);
        }

        if ($this->sameStoredPayment($order, $paymentId) && $order->needsPaymentReview()) {
            return redirect()->route('checkout.pay', $order);
        }

        if ($this->sameStoredPayment($order, $paymentId) && $order->isFulfilled()) {
            return redirect()->route('checkout.success', $order);
        }

        try {
            $this->payments->verifyAndComplete($order, $paymentId, $submittedOrderId, $signature);
        } catch (RazorpayReconciliationRequiredException) {
            return redirect()->route('checkout.pay', $order);
        } catch (RuntimeException) {
            return redirect()->route('checkout.pay', $order);
        }

        return redirect()->route('checkout.success', $order->fresh());
    }

    /**
     * The callback carries no session, so ownership cannot be proven by cookie.
     * A signature over this order's own stored Razorpay order ID proves the
     * gateway sent it and cannot be replayed against a different local order.
     */
    private function callbackIsAuthentic(
        Order $order,
        string $submittedOrderId,
        string $paymentId,
        string $signature,
    ): bool {
        if (! $this->razorpay->isConfigured()) {
            return false;
        }

        $storedOrderId = (string) $order->razorpay_order_id;

        if ($storedOrderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        if (! hash_equals($storedOrderId, $submittedOrderId)) {
            return false;
        }

        return $this->razorpay->verifySignature($storedOrderId, $paymentId, $signature);
    }

    private function sameStoredPayment(Order $order, string $paymentId): bool
    {
        $stored = (string) $order->payment_id;

        return $stored !== '' && $paymentId !== '' && hash_equals($stored, $paymentId);
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }
}
