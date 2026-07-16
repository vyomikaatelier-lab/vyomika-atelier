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

        if (! $this->razorpay->isConfigured()) {
            return redirect()->route('checkout.index')
                ->with('error', 'Online payment is not available right now. Please contact us to complete your order.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:60',
            'last_name' => 'nullable|string|max:60',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'country_other' => 'nullable|required_if:country,Other|string|max:100',
            'payment_method' => 'required|in:razorpay',
            'notes' => 'nullable|string|max:1000',
            'company' => 'nullable|string|max:255',
        ]);

        if ($request->filled('first_name')) {
            $validated['customer_name'] = trim($request->input('first_name') . ' ' . $request->input('last_name'));
        }

        $country = $validated['country'] === 'Other'
            ? trim((string) ($validated['country_other'] ?? ''))
            : $validated['country'];

        $noteLines = array_filter([
            $validated['notes'] ?? null,
            $request->filled('company') ? 'Company: ' . $request->input('company') : null,
            $country ? 'Country/Region: ' . $country : null,
        ]);
        $validated['notes'] = $noteLines ? implode("\n", $noteLines) : null;

        unset($validated['first_name'], $validated['last_name'], $validated['company'], $validated['country'], $validated['country_other']);

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

        $razorpayOrder = $this->razorpay->createOrder($order);

        if (! $razorpayOrder) {
            return redirect()->route('checkout.index')
                ->with('error', 'Could not start payment. Please try again.');
        }

        $order->update(['razorpay_order_id' => $razorpayOrder['id']]);
        $this->cart->clear();

        return redirect()->route('checkout.pay', $order);
    }

    public function success(Order $order)
    {
        $order->load('items');

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
