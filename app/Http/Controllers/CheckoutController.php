<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AddressValidationService;
use App\Services\CartService;
use App\Services\OrderNotificationService;
use App\Services\RazorpayService;
use App\Services\StockAvailability;
use App\Support\CartGuard;
use App\Support\CheckoutCustomer;
use App\Support\OrderAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private RazorpayService $razorpay,
        private OrderNotificationService $notifications,
        private AddressValidationService $addresses,
    ) {}

    public function index()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.index')->with('error', 'Your cart is empty.');
        }

        $user = Auth::user();
        $items = $this->cart->all();
        $subtotal = $this->cart->subtotal();
        $shipping = $subtotal >= 5000 ? 0 : 199;
        $total = $subtotal + $shipping;
        $defaultAddress = $user?->addresses()->where('is_default', true)->first()
            ?? $user?->addresses()->first();

        return view('checkout.index', [
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'razorpayEnabled' => $this->razorpay->isConfigured(),
            'defaultAddress' => $defaultAddress,
            'user' => $user,
        ]);
    }

    public function store(Request $request)
    {
        if ($message = CheckoutCustomer::denialMessage(Auth::user())) {
            return redirect()->route('checkout.index')->with('error', $message);
        }

        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.index')->with('error', 'Your cart is empty.');
        }

        if (! $this->razorpay->isConfigured()) {
            return redirect()->route('checkout.index')
                ->with('error', config('addresses.payment_unavailable_message'));
        }

        $pendingId = session(OrderAccess::SESSION_KEY);
        if ($pendingId) {
            $existing = Order::query()->find($pendingId);
            if ($existing && $existing->status === 'pending' && ! $existing->isExpired()) {
                return redirect()->route('checkout.pay', $existing)
                    ->with('info', 'You already have an order awaiting payment. Complete payment or wait for it to expire before placing a new order.');
            }
        }

        $ineligible = $this->cart->all()->first(
            fn (array $item) => ! CartGuard::isEligible($item['product'])
        );

        if ($ineligible) {
            return redirect()->route('checkout.index')
                ->with('error', CartGuard::checkoutEligibility($ineligible['product']));
        }

        try {
            $addressInput = $this->addresses->mapCheckoutInput($request->all());
            $validatedAddress = $this->addresses->validate($addressInput, true);
        } catch (ValidationException $e) {
            return redirect()->route('checkout.index')
                ->withErrors($e->errors())
                ->withInput();
        }

        $snapshot = $this->addresses->toSnapshot($validatedAddress);
        $user = Auth::user();

        $noteLines = array_filter([
            $validatedAddress['delivery_instructions'] ?? null,
            $validatedAddress['notes'] ?? null,
            filled($validatedAddress['company'] ?? null) ? 'Company: ' . $validatedAddress['company'] : null,
            $snapshot['country'] ? 'Country/Region: ' . $snapshot['country'] : null,
        ]);

        $items = $this->cart->all();
        $subtotal = $this->cart->subtotal();
        $shipping = $subtotal >= 5000 ? 0 : 199;
        $total = $subtotal + $shipping;

        foreach ($items as $item) {
            $available = StockAvailability::availableForProduct($item['product']);

            if ($item['quantity'] > $available) {
                return redirect()->route('cart.index')
                    ->with('error', "{$item['product']->name} only has {$available} available. Please update your cart.");
            }
        }

        $checkoutToken = $request->session()->get('checkout_submit_token');
        if ($checkoutToken) {
            $duplicate = Order::query()
                ->where('checkout_token', $checkoutToken)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subHour())
                ->first();

            if ($duplicate && ! $duplicate->isExpired()) {
                return redirect()->route('checkout.pay', $duplicate)
                    ->with('info', 'Resuming your pending order.');
            }
        }

        $checkoutToken = (string) Str::uuid();
        $request->session()->put('checkout_submit_token', $checkoutToken);

        $order = DB::transaction(function () use ($request, $snapshot, $validatedAddress, $noteLines, $items, $subtotal, $shipping, $total, $user, $checkoutToken) {
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => Order::generateOrderNumber(),
                'customer_name' => $snapshot['full_name'],
                'customer_email' => $snapshot['email'],
                'customer_phone' => $snapshot['phone'],
                'alt_mobile' => $snapshot['alt_mobile'],
                'shipping_address' => $snapshot['formatted_line'],
                'city' => $snapshot['city'],
                'state' => $snapshot['state'],
                'pincode' => $snapshot['pincode'],
                'country' => $snapshot['country'],
                'subtotal' => $subtotal,
                'shipping_cost' => $shipping,
                'total' => $total,
                'status' => 'pending',
                'payment_method' => 'razorpay',
                'notes' => $noteLines ? implode("\n", $noteLines) : null,
                'shipping_snapshot' => $snapshot,
                'billing_snapshot' => $validatedAddress['billing_same_as_shipping'] ? $snapshot : null,
                'checkout_token' => $checkoutToken,
                'expires_at' => now()->addHours(Order::pendingExpiryHours()),
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'finish_slug' => $item['finish_slug'],
                    'finish_name' => $item['finish_name'],
                    'price' => $item['product']->price,
                    'quantity' => $item['quantity'],
                    'total' => $item['line_total'],
                ]);
            }

            if ($request->boolean('save_address')) {
                    $user->addresses()->create([
                        'label' => ucfirst($snapshot['address_type']),
                        'name' => $snapshot['full_name'],
                        'phone' => $snapshot['phone'],
                        'alt_mobile' => $snapshot['alt_mobile'],
                        'email' => $snapshot['email'],
                        'address_line1' => $snapshot['formatted_line'],
                        'house_building' => $snapshot['house_building'],
                        'street' => $snapshot['street'],
                        'locality' => $snapshot['locality'],
                        'landmark' => $snapshot['landmark'],
                        'city' => $snapshot['city'],
                        'state' => $snapshot['state'],
                        'pincode' => $snapshot['pincode'],
                        'country' => $snapshot['country'],
                        'address_type' => $snapshot['address_type'],
                        'floor' => $snapshot['floor'],
                        'lift_available' => $snapshot['lift_available'],
                        'delivery_instructions' => $snapshot['delivery_instructions'],
                        'billing_same_as_shipping' => $snapshot['billing_same_as_shipping'],
                        'pin_lookup_status' => $snapshot['pin_lookup_status'],
                        'is_default' => $user->addresses()->count() === 0,
                    ]);
            }

            return $order;
        });

        $razorpayOrder = $this->razorpay->createOrder($order);

        if (! $razorpayOrder) {
            return redirect()->route('checkout.index')
                ->with('error', 'Could not start payment. Please try again.');
        }

        $order->update(['razorpay_order_id' => $razorpayOrder['id']]);
        OrderAccess::remember($order);

        $emailSent = $this->notifications->sendOrderReceived($order->fresh('items'));

        return redirect()->route('checkout.pay', $order)
            ->with('order_email_sent', $emailSent);
    }

    public function success(Order $order)
    {
        if (! OrderAccess::canAccess($order)) {
            return redirect()->route('shop.index')->with('error', 'Order not found.');
        }

        $order->load('items');

        return view('checkout.success', [
            'order' => $order,
            'orderEmailSent' => $order->order_received_email_sent_at !== null,
            'paymentEmailSent' => $order->payment_email_sent_at !== null,
        ]);
    }
}
