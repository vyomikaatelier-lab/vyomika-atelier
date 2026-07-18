<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderNotificationService;
use App\Services\RazorpayService;
use App\Services\StockAvailability;
use App\Support\OrderAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private RazorpayService $razorpay,
        private OrderNotificationService $notifications,
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
            'razorpayKey' => config('services.razorpay.key'),
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

        if ($order->razorpay_order_id && $validated['razorpay_order_id'] !== $order->razorpay_order_id) {
            return redirect()->route('checkout.pay', $order)
                ->with('error', 'Payment does not match this order. Please try again.');
        }

        if (! $this->razorpay->verifySignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        )) {
            return redirect()->route('checkout.pay', $order)
                ->with('error', 'Payment verification failed. Please try again.');
        }

        try {
            DB::transaction(function () use ($order, $validated) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                if ($locked->status !== 'pending') {
                    return;
                }

                $locked->update([
                    'status' => 'paid',
                    'payment_id' => $validated['razorpay_payment_id'],
                    'razorpay_order_id' => $validated['razorpay_order_id'],
                    'expires_at' => null,
                ]);

                StockAvailability::deductForPaidOrder($locked->fresh('items.product'));
            });
        } catch (\RuntimeException $e) {
            Log::error('Payment verified but stock deduction failed.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('checkout.pay', $order)
                ->with('error', 'Payment was received but stock is no longer available. Please contact us.');
        }

        $order->refresh();
        $this->notifications->sendPaymentConfirmed($order);

        return redirect()->route('checkout.success', $order);
    }
}
