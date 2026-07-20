<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPaymentService;
use App\Services\RazorpayService;
use App\Support\OrderAccess;
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
            return redirect()->route('shop.index')->with('error', 'Order not found.');
        }

        if ($order->payment_method !== 'razorpay' || $order->status !== 'pending') {
            return redirect()->route('checkout.success', $order);
        }

        if ($order->isExpired()) {
            return redirect()->route('shop.index')
                ->with('error', 'This order has expired. Please place a new order.');
        }

        if (! $this->razorpay->isConfigured()) {
            return redirect()->route('checkout.index')
                ->with('error', 'Online payment is not configured yet. Please contact us to complete your order.');
        }

        return view('checkout.pay', [
            'order' => $order,
            'razorpayKey' => $this->razorpay->key(),
        ]);
    }

    public function verify(Request $request, Order $order)
    {
        if (! OrderAccess::canAccess($order)) {
            return redirect()->route('shop.index')->with('error', 'Order not found.');
        }

        if ($order->status !== 'pending') {
            return redirect()->route('checkout.success', $order);
        }

        if ($order->isExpired()) {
            return redirect()->route('shop.index')
                ->with('error', 'This order has expired. Please place a new order.');
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
        } catch (RuntimeException $e) {
            return redirect()->route('checkout.pay', $order)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('checkout.success', $order->fresh());
    }
}
