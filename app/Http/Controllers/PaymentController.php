<?php

namespace App\Http\Controllers;

use App\Exceptions\RazorpayReconciliationRequiredException;
use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
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
        if (! OrderAccess::canAccess($order)) {
            return redirect(StorefrontRoutes::primaryShopUrl())->with('error', 'Order not found.');
        }

        if ($order->isFulfilled()) {
            return redirect()->route('checkout.success', $order);
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

        return redirect()->route('checkout.success', $order->fresh());
    }
}
