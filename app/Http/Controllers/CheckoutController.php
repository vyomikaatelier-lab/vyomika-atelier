<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CartService;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private RazorpayService $razorpay
    ) {}

    public function index()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.index')->with('error', 'Your cart is empty.');
        }

        $items = $this->cart->all();
        $subtotal = $this->cart->subtotal();
        $shipping = $subtotal >= 5000 ? 0 : 199;
        $total = $subtotal + $shipping;

        return view('checkout.index', [
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'razorpayEnabled' => $this->razorpay->isConfigured(),
        ]);
    }

    public function store(Request $request)
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.index')->with('error', 'Your cart is empty.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'required|string|max:10',
            'payment_method' => 'required|in:cod,bank_transfer'.($this->razorpay->isConfigured() ? ',razorpay' : ''),
            'notes' => 'nullable|string|max:1000',
        ]);

        $items = $this->cart->all();
        $subtotal = $this->cart->subtotal();
        $shipping = $subtotal >= 5000 ? 0 : 199;
        $total = $subtotal + $shipping;

        $order = DB::transaction(function () use ($validated, $items, $subtotal, $shipping, $total) {
            $order = Order::create([
                ...$validated,
                'order_number' => Order::generateOrderNumber(),
                'subtotal' => $subtotal,
                'shipping_cost' => $shipping,
                'total' => $total,
                'status' => 'pending',
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'price' => $item['product']->price,
                    'quantity' => $item['quantity'],
                    'total' => $item['line_total'],
                ]);

                $item['product']->decrement('stock', $item['quantity']);
            }

            return $order;
        });

        $this->cart->clear();

        if ($validated['payment_method'] === 'razorpay') {
            $razorpayOrder = $this->razorpay->createOrder($order);

            if ($razorpayOrder) {
                $order->update(['razorpay_order_id' => $razorpayOrder['id']]);

                return redirect()->route('checkout.pay', $order);
            }
        }

        $this->notifyAdmin($order);

        return redirect()->route('checkout.success', $order);
    }

    public function success(Order $order)
    {
        return view('checkout.success', compact('order'));
    }

    private function notifyAdmin(Order $order): void
    {
        $adminEmail = config('services.admin_email');

        if ($adminEmail) {
            Mail::raw(
                "New order {$order->order_number} from {$order->customer_name}. Total: ₹{$order->total}",
                fn ($message) => $message->to($adminEmail)->subject("New Order: {$order->order_number}")
            );
        }
    }
}
