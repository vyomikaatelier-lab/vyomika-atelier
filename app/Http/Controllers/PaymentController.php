<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    public function __construct(private RazorpayService $razorpay) {}

    public function show(Order $order)
    {
        if ($order->payment_method !== 'razorpay' || $order->status !== 'pending') {
            return redirect()->route('checkout.success', $order);
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
        $validated = $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        if (! $this->razorpay->verifySignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        )) {
            return redirect()->route('checkout.pay', $order)
                ->with('error', 'Payment verification failed. Please try again.');
        }

        $order->update([
            'status' => 'paid',
            'payment_id' => $validated['razorpay_payment_id'],
            'razorpay_order_id' => $validated['razorpay_order_id'],
        ]);

        $adminEmail = config('services.admin_email');
        if ($adminEmail) {
            Mail::raw(
                "Payment received for order {$order->order_number}. Amount: ₹{$order->total}",
                fn ($message) => $message->to($adminEmail)->subject("Payment Received: {$order->order_number}")
            );
        }

        return redirect()->route('checkout.success', $order);
    }
}
