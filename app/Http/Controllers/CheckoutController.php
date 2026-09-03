<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AddressValidationService;
use App\Services\CartService;
use App\Services\OrderNotificationService;
use App\Services\OrderPaymentService;
use App\Services\PendingOrderExpiry;
use App\Services\RazorpayService;
use App\Services\StockAvailability;
use App\Support\CartGuard;
use App\Support\CheckoutCustomer;
use App\Support\CheckoutSnapshot;
use App\Support\OrderAccess;
use App\Support\PaymentAtomicLock;
use App\Support\StorefrontRoutes;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CheckoutController extends Controller
{
    public const MSG_ACTIVE_PAYMENT = 'An active payment is already awaiting completion. Finish that payment or wait for it to expire before placing a different order.';

    public const MSG_CHECKOUT_IN_PROGRESS = 'Another checkout is already in progress. Please wait a moment and try again.';

    public function __construct(
        private CartService $cart,
        private RazorpayService $razorpay,
        private OrderNotificationService $notifications,
        private AddressValidationService $addresses,
        private OrderPaymentService $payments,
    ) {}

    public function index()
    {
        if ($this->cart->checkoutIsEmpty()) {
            return redirect(StorefrontRoutes::primaryShopUrl())->with('error', 'Your cart is empty.');
        }

        $user = Auth::user();
        $items = $this->cart->checkoutItems();
        $subtotal = $this->cart->checkoutSubtotal();
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

        if ($this->cart->checkoutIsEmpty()) {
            return redirect(StorefrontRoutes::primaryShopUrl())->with('error', 'Your cart is empty.');
        }

        if (! $this->razorpay->isConfigured()) {
            return redirect()->route('checkout.index')
                ->with('error', config('addresses.payment_unavailable_message'));
        }

        $ineligible = $this->cart->checkoutItems()->first(
            fn (array $item) => ! CartGuard::isEligible($item['product'], $item['size_label'] ?? null)
                || ($item['unit_price'] ?? 0) <= 0
        );

        if ($ineligible) {
            return redirect()->route('checkout.index')
                ->with('error', CartGuard::checkoutEligibility(
                    $ineligible['product'],
                    $ineligible['size_label'] ?? null
                ) ?? CartGuard::MSG_NO_PRICE);
        }

        if ($message = CartGuard::checkoutItemsEligible($this->cart->checkoutItems())) {
            return redirect()->route('checkout.index')->with('error', $message);
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

        $fromBuyNow = $this->cart->hasBuyNow();
        $source = $fromBuyNow ? CheckoutSnapshot::SOURCE_BUY_NOW : CheckoutSnapshot::SOURCE_CART;
        $items = $this->cart->checkoutItems();
        $subtotal = $this->cart->checkoutSubtotal();
        $shipping = $subtotal >= 5000 ? 0 : 199;
        $total = $subtotal + $shipping;

        if ($subtotal <= 0 || $total <= 0) {
            return redirect()->route('cart.index')
                ->with('error', CartGuard::MSG_NO_PRICE);
        }

        foreach ($items as $item) {
            $available = StockAvailability::availableForProduct($item['product']);

            if ($item['quantity'] > $available) {
                return redirect()->route('cart.index')
                    ->with('error', "{$item['product']->name} only has {$available} available. Please update your cart.");
            }
        }

        if ($message = CartGuard::checkoutItemsEligible($items)) {
            return redirect()->route('cart.index')->with('error', $message);
        }

        $desiredSnapshot = CheckoutSnapshot::fromCheckout(
            $source,
            $items,
            $subtotal,
            $shipping,
            $total,
            $snapshot,
        );

        try {
            $result = PaymentAtomicLock::run(
                PaymentAtomicLock::forCustomer((int) $user->id),
                PaymentAtomicLock::customerWaitSeconds(),
                fn () => $this->selectOrCreatePayableOrder(
                    $request,
                    $user->id,
                    $source,
                    $fromBuyNow,
                    $desiredSnapshot,
                    $snapshot,
                    $validatedAddress,
                    $noteLines,
                    $items,
                    $subtotal,
                    $shipping,
                    $total,
                ),
            );
        } catch (LockTimeoutException) {
            return redirect()->route('checkout.index')
                ->with('error', self::MSG_CHECKOUT_IN_PROGRESS);
        } catch (RuntimeException $e) {
            return redirect()->route('checkout.index')
                ->with('error', $e->getMessage() ?: 'Could not start payment. Please try again.');
        }

        return $result;
    }

    public function success(Order $order)
    {
        if (! OrderAccess::canAccess($order)) {
            return redirect(StorefrontRoutes::primaryShopUrl())->with('error', 'Order not found.');
        }

        $order->load('items');

        if ($order->isFulfilled()) {
            return view('checkout.success', [
                'order' => $order,
                'orderEmailSent' => $order->order_received_email_sent_at !== null,
                'paymentEmailSent' => $order->payment_email_sent_at !== null,
            ]);
        }

        if ($order->isCancelled()) {
            return view('checkout.payment-cancelled', ['order' => $order]);
        }

        if ($order->isExpired()) {
            return view('checkout.payment-expired', ['order' => $order]);
        }

        if ($order->isAwaitingPayment()) {
            return redirect()->route('checkout.pay', $order);
        }

        return redirect(StorefrontRoutes::primaryShopUrl())
            ->with('error', 'This order is not awaiting payment.');
    }

    /**
     * @param  array<string, mixed>  $desiredSnapshot
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $validatedAddress
     * @param  array<int, string|null>  $noteLines
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     */
    private function selectOrCreatePayableOrder(
        Request $request,
        int $userId,
        string $source,
        bool $fromBuyNow,
        array $desiredSnapshot,
        array $snapshot,
        array $validatedAddress,
        array $noteLines,
        $items,
        float $subtotal,
        float $shipping,
        float $total,
    ): RedirectResponse {
        $this->expireStalePendingOrders($userId);

        $existing = $this->activePayableOrderFor($userId);

        if ($existing) {
            if (CheckoutSnapshot::matches(CheckoutSnapshot::fromOrder($existing), $desiredSnapshot)) {
                return $this->resumePayableOrder($existing);
            }

            return redirect()->route('checkout.index')
                ->with('error', self::MSG_ACTIVE_PAYMENT)
                ->with('resume_payment_url', route('checkout.pay', $existing))
                ->with('resume_order_number', $existing->order_number);
        }

        $order = $this->createLocalOrder(
            $request,
            $userId,
            $source,
            $snapshot,
            $validatedAddress,
            $noteLines,
            $items,
            $subtotal,
            $shipping,
            $total,
        );

        $this->ensureRazorpayOrderOrFail($order);

        OrderAccess::remember($order);

        if ($fromBuyNow) {
            $request->session()->put(CartService::CHECKOUT_SOURCE_KEY, 'buy_now');
            $this->cart->clearBuyNow();
        }

        $emailSent = $this->notifications->sendOrderReceived($order->fresh('items'));

        return redirect()->route('checkout.pay', $order)
            ->with('order_email_sent', $emailSent);
    }

    private function resumePayableOrder(Order $order): RedirectResponse
    {
        $this->ensureRazorpayOrderOrFail($order);
        OrderAccess::remember($order);

        return redirect()->route('checkout.pay', $order)
            ->with('info', 'Resuming your pending order.');
    }

    private function ensureRazorpayOrderOrFail(Order $order): void
    {
        try {
            $this->payments->ensureRazorpayOrderId($order);
        } catch (RuntimeException $e) {
            throw new RuntimeException(
                $e->getMessage() !== '' ? $e->getMessage() : 'Could not start payment. Please try again.',
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function expireStalePendingOrders(int $userId): void
    {
        Order::query()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->each(fn (Order $order) => PendingOrderExpiry::expireIfStillPending($order));
    }

    private function activePayableOrderFor(int $userId): ?Order
    {
        return Order::query()
            ->with('items')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $validatedAddress
     * @param  array<int, string|null>  $noteLines
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $items
     */
    private function createLocalOrder(
        Request $request,
        int $userId,
        string $source,
        array $snapshot,
        array $validatedAddress,
        array $noteLines,
        $items,
        float $subtotal,
        float $shipping,
        float $total,
    ): Order {
        $checkoutToken = (string) Str::uuid();
        $request->session()->put('checkout_submit_token', $checkoutToken);
        $shippingSnapshot = CheckoutSnapshot::withSource($snapshot, $source);

        return DB::transaction(function () use (
            $request,
            $userId,
            $shippingSnapshot,
            $snapshot,
            $validatedAddress,
            $noteLines,
            $items,
            $subtotal,
            $shipping,
            $total,
            $checkoutToken,
        ) {
            $user = Auth::user();

            $order = Order::create([
                'user_id' => $userId,
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
                'shipping_snapshot' => $shippingSnapshot,
                'billing_snapshot' => $validatedAddress['billing_same_as_shipping'] ? $shippingSnapshot : null,
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
                    'size_label' => $item['size_label'],
                    'price' => $item['unit_price'],
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
    }
}
