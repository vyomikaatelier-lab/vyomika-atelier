<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AddressValidationService;
use App\Services\CartService;
use App\Services\ChargeConfirmationEnquiry;
use App\Services\InternationalShippingEnquiry;
use App\Services\OrderNotificationService;
use App\Services\OrderPaymentService;
use App\Services\PendingOrderExpiry;
use App\Services\RazorpayService;
use App\Services\StockAvailability;
use App\Support\CartGuard;
use App\Support\CheckoutCustomer;
use App\Support\CheckoutPayments;
use App\Support\CheckoutSnapshot;
use App\Support\IndiaDelivery;
use App\Support\ProductFulfilment;
use App\Support\OrderAccess;
use App\Support\PaymentAtomicLock;
use App\Support\StorefrontRoutes;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        private InternationalShippingEnquiry $internationalEnquiries,
        private ChargeConfirmationEnquiry $chargeEnquiries,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $items = $this->cart->checkoutItems();
        $quote = ProductFulfilment::quote($items, true);
        $subtotal = $quote['lines'] === []
            ? $this->cart->checkoutSubtotal()
            : (float) \App\Services\RefundMoney::formatRupees((int) $quote['merchandise_paise']);
        $shipping = $quote['shipping_paise'] === null ? 0.0 : (float) \App\Services\RefundMoney::formatRupees((int) $quote['shipping_paise']);
        $packing = $quote['packing_paise'] === null ? 0.0 : (float) \App\Services\RefundMoney::formatRupees((int) $quote['packing_paise']);
        $total = $subtotal + $shipping + $packing;
        $defaultAddress = $user?->addresses()->where('is_default', true)->first()
            ?? $user?->addresses()->first();
        $checkoutView = [
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'packing' => $packing,
            'total' => $total,
            'fulfilmentQuote' => $quote,
            'razorpayEnabled' => $this->razorpay->isConfigured(),
            'defaultAddress' => $defaultAddress,
            'user' => $user,
            'paymentsEnabled' => CheckoutPayments::enabled(),
        ];

        if (! CheckoutPayments::enabled()) {
            return view('checkout.unavailable', $checkoutView);
        }

        if ($this->cart->checkoutIsEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        return view('checkout.index', $checkoutView);
    }

    public function store(Request $request)
    {
        if ($message = CheckoutCustomer::denialMessage(Auth::user())) {
            return redirect()->route('checkout.index')->with('error', $message);
        }

        if (CheckoutPayments::initiationDenied(Auth::user())) {
            return redirect()
                ->route('checkout.index')
                ->with('error', CheckoutPayments::UNAVAILABLE_MESSAGE);
        }

        if ($this->cart->checkoutIsEmpty()) {
            if (! CheckoutPayments::canInitiate(Auth::user())) {
                return redirect()
                    ->route('checkout.index')
                    ->with('error', CheckoutPayments::UNAVAILABLE_MESSAGE);
            }

            $user = Auth::user();
            if ($user) {
                $this->expireStalePendingOrders((int) $user->id);
                $existing = $this->activePayableOrderFor((int) $user->id);
                if ($response = $this->responseForObsoleteShipping($existing, false)) {
                    return $response;
                }
                if ($existing && IndiaDelivery::canInitiateSelfServicePayment($existing)) {
                    return $this->resumePayableOrder($existing);
                }
            }

            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
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

        if (! IndiaDelivery::isIndia($validatedAddress['country'] ?? null)) {
            return $this->internationalEnquiries->store(
                $request,
                $this->cart->checkoutItems(),
                $validatedAddress,
            );
        }

        $snapshot = $this->addresses->toSnapshot($validatedAddress);
        $user = Auth::user();
        $items = $this->cart->checkoutItems();
        $quote = ProductFulfilment::quote($items, true);

        if (! $quote['payable']) {
            if (CheckoutPayments::enabled()) {
                if ($response = $this->responseForChangedTerms((int) $user->id, $items, $quote)) {
                    return $response;
                }
            }

            return $this->chargeEnquiries->store($request, $items, $validatedAddress, $quote);
        }

        if (! CheckoutPayments::canInitiate(Auth::user())) {
            return redirect()
                ->route('checkout.index')
                ->withInput()
                ->with('error', CheckoutPayments::UNAVAILABLE_MESSAGE);
        }

        if (! $this->razorpay->isConfigured()) {
            return redirect()->route('checkout.index')
                ->with('error', config('addresses.payment_unavailable_message'));
        }

        $noteLines = array_filter([
            $validatedAddress['delivery_instructions'] ?? null,
            $validatedAddress['notes'] ?? null,
            filled($validatedAddress['company'] ?? null) ? 'Company: '.$validatedAddress['company'] : null,
            $snapshot['country'] ? 'Country/Region: '.$snapshot['country'] : null,
        ]);

        $fromBuyNow = $this->cart->hasBuyNow();
        $source = $fromBuyNow ? CheckoutSnapshot::SOURCE_BUY_NOW : CheckoutSnapshot::SOURCE_CART;
        $subtotal = \App\Services\RefundMoney::formatRupees((int) $quote['merchandise_paise']);
        $shippingAmount = \App\Services\RefundMoney::formatRupees((int) $quote['shipping_paise']);
        $packingAmount = \App\Services\RefundMoney::formatRupees((int) $quote['packing_paise']);
        $totalAmount = \App\Services\RefundMoney::formatRupees(
            (int) $quote['merchandise_paise'] + (int) $quote['shipping_paise'] + (int) $quote['packing_paise']
        );
        $shipping = (float) $shippingAmount;
        $packing = (float) $packingAmount;
        $total = (float) $totalAmount;
        $fulfilment = ProductFulfilment::orderSnapshot($quote);

        if ((float) $subtotal <= 0 || (float) $total <= 0) {
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
            $shippingAmount,
            $totalAmount,
            $snapshot,
            $packingAmount,
        );

        try {
            $result = PaymentAtomicLock::run(
                PaymentAtomicLock::forCustomer((int) $user->id),
                PaymentAtomicLock::customerWaitSeconds(),
                fn () => $this->selectOrCreatePayableOrder(
                    $request,
                    $user->id,
                    $source,
                    $desiredSnapshot,
                    $snapshot,
                    $validatedAddress,
                    $noteLines,
                    $items,
                    $subtotal,
                    $shippingAmount,
                    $totalAmount,
                    $packingAmount,
                    $fulfilment,
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

        if ($order->needsPaymentReview()) {
            return view('checkout.payment-review', ['order' => $order]);
        }

        if ($order->showsRefundedCancellation()) {
            return view('checkout.payment-refunded', ['order' => $order]);
        }

        if ($order->showsCapturedPaymentConfirmation()) {
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
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function selectOrCreatePayableOrder(
        Request $request,
        int $userId,
        string $source,
        array $desiredSnapshot,
        array $snapshot,
        array $validatedAddress,
        array $noteLines,
        $items,
        string $subtotal,
        string $shipping,
        string $total,
        string $packing,
        array $fulfilment,
    ): RedirectResponse {
        $this->expireStalePendingOrders($userId);

        $existing = $this->activePayableOrderFor($userId);

        if ($existing && ! IndiaDelivery::isIndia($existing->country)) {
            $existing = null;
        }

        if ($response = $this->responseForObsoleteShipping($existing, true)) {
            return $response;
        }

        $existing = $this->activePayableOrderFor($userId);
        if ($existing && ! IndiaDelivery::isIndia($existing->country)) {
            $existing = null;
        }

        if ($existing) {
            if (CheckoutSnapshot::matches(CheckoutSnapshot::fromOrder($existing), $desiredSnapshot)) {
                $redirect = $this->resumePayableOrder($existing);
                $this->cart->consumeCheckedOutItems($items);

                return $redirect;
            }

            if ($this->sameCheckoutItems($existing, $desiredSnapshot)
                && $existing->lacksPaymentEvidence()
                && ! IndiaDelivery::hasObsoleteShippingCharge($existing)) {
                $retired = $this->payments->retireUntouchedStaleTerms($existing);
                if ($retired) {
                    session()->flash('info', ProductFulfilment::TERMS_UPDATED);
                    $existing = null;
                }
            }

            if ($existing) {
                return redirect()->route('checkout.index')
                    ->with('error', self::MSG_ACTIVE_PAYMENT)
                    ->with('resume_payment_url', route('checkout.pay', $existing))
                    ->with('resume_order_number', $existing->order_number);
            }
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
            $packing,
            $fulfilment,
        );

        $this->ensureRazorpayOrderOrFail($order);

        OrderAccess::remember($order);

        $this->cart->consumeCheckedOutItems($items);

        $emailSent = $this->notifications->sendOrderReceived($order->fresh('items'));

        return redirect()->route('checkout.pay', $order)
            ->with('order_email_sent', $emailSent);
    }

    private function responseForObsoleteShipping(?Order $existing, bool $retireUntouched = false): ?RedirectResponse
    {
        if (! $existing
            || ! IndiaDelivery::isIndia($existing->country)
            || ! IndiaDelivery::hasObsoleteShippingCharge($existing)) {
            return null;
        }

        try {
            if ($retireUntouched) {
                $this->payments->retireUntouchedObsoleteShipping($existing);
            } else {
                $this->payments->assertInitiationAllowed($existing);
            }
        } catch (LockTimeoutException) {
            return redirect()->route('checkout.index')->with('error', self::MSG_CHECKOUT_IN_PROGRESS);
        } catch (RuntimeException $e) {
            if ($retireUntouched && $e->getMessage() === IndiaDelivery::STALE_ORDER_RETIRED) {
                session()->flash('info', IndiaDelivery::STALE_ORDER_RETIRED);

                return null;
            }

            return redirect()
                ->route($retireUntouched ? 'checkout.index' : 'cart.index')
                ->with('error', $e->getMessage() ?: IndiaDelivery::STALE_SHIPPING_SUPPORT);
        }

        return null;
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
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function createLocalOrder(
        Request $request,
        int $userId,
        string $source,
        array $snapshot,
        array $validatedAddress,
        array $noteLines,
        $items,
        string $subtotal,
        string $shipping,
        string $total,
        string $packing,
        array $fulfilment,
    ): Order {
        $checkoutToken = (string) Str::uuid();
        $request->session()->put('checkout_submit_token', $checkoutToken);
        $shippingSnapshot = CheckoutSnapshot::withSource($snapshot, $source);
        if (CheckoutPayments::shouldSuppressOrderMail(Auth::user())) {
            $shippingSnapshot[CheckoutPayments::SNAPSHOT_SUPPRESS_NOTIFICATIONS] = true;
        }

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
            $packing,
            $fulfilment,
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
                'packing_cost' => $packing,
                'total' => $total,
                'status' => 'pending',
                'payment_method' => 'razorpay',
                'notes' => $noteLines ? implode("\n", $noteLines) : null,
                'shipping_snapshot' => $shippingSnapshot,
                'fulfilment_snapshot' => $fulfilment,
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
                    'fulfilment_snapshot' => $this->lineSnapshot($fulfilment, (int) $item['product']->id, (int) $item['quantity'], $item['size_label'] ?? null),
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

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $quote
     */
    private function responseForChangedTerms(int $userId, $items, array $quote): ?RedirectResponse
    {
        $this->expireStalePendingOrders($userId);
        $existing = $this->activePayableOrderFor($userId);
        if (! $existing || ! IndiaDelivery::isIndia($existing->country)) {
            return null;
        }

        $stored = CheckoutSnapshot::fromOrder($existing);
        $sameItems = $this->sameCheckoutItems($existing, [
            'items' => collect($items)->map(fn (array $item) => [
                'product_id' => (int) $item['product']->id,
                'size_label' => $item['size_label'] ?? null,
                'finish_slug' => $item['finish_slug'] ?? null,
                'quantity' => (int) $item['quantity'],
            ])->all(),
        ]);

        if (! $sameItems) {
            return redirect()->route('checkout.index')
                ->with('error', self::MSG_ACTIVE_PAYMENT)
                ->with('resume_payment_url', route('checkout.pay', $existing))
                ->with('resume_order_number', $existing->order_number);
        }

        if (! $existing->lacksPaymentEvidence()) {
            return redirect()->route('checkout.index')
                ->with('error', self::MSG_ACTIVE_PAYMENT)
                ->with('resume_payment_url', route('checkout.pay', $existing))
                ->with('resume_order_number', $existing->order_number);
        }

        if ($quote['payable']) {
            return null;
        }

        $shipping = \App\Services\RefundMoney::formatRupees((int) ($quote['shipping_paise'] ?? 0));
        if (($stored['shipping_cost'] ?? null) === $shipping && ($stored['packing_cost'] ?? '0.00') === '0.00' && $quote['reasons'] === []) {
            return null;
        }

        try {
            if ($this->payments->retireUntouchedStaleTerms($existing)) {
                session()->flash('info', ProductFulfilment::TERMS_UPDATED);
            }
        } catch (LockTimeoutException) {
            return redirect()->route('checkout.index')->with('error', self::MSG_CHECKOUT_IN_PROGRESS);
        } catch (RuntimeException $e) {
            return redirect()->route('checkout.index')->with('error', $e->getMessage() ?: self::MSG_CHECKOUT_IN_PROGRESS);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $desiredSnapshot
     */
    private function sameCheckoutItems(Order $order, array $desiredSnapshot): bool
    {
        $stored = CheckoutSnapshot::fromOrder($order);
        $left = $stored['items'] ?? [];
        $right = $desiredSnapshot['items'] ?? [];
        $simplify = function (array $items): array {
            $rows = array_map(function (array $item): array {
                return [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'size_label' => strtolower(trim((string) ($item['size_label'] ?? ''))),
                    'finish_slug' => strtolower(trim((string) ($item['finish_slug'] ?? ''))),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ];
            }, $items);
            usort($rows, fn (array $left, array $right): int => [$left['product_id'], $left['size_label'], $left['finish_slug']]
                <=> [$right['product_id'], $right['size_label'], $right['finish_slug']]);

            return $rows;
        };

        return $simplify($left) === $simplify($right);
    }

    /**
     * @param  array<string, mixed>  $fulfilment
     * @return array<string, mixed>|null
     */
    private function lineSnapshot(array $fulfilment, int $productId, int $quantity, mixed $itemSize = null): ?array
    {
        foreach ($fulfilment['lines'] ?? [] as $line) {
            if ((int) ($line['product_id'] ?? 0) !== $productId || (int) ($line['quantity'] ?? 0) !== $quantity) {
                continue;
            }
            if (($line['size_label'] ?? null) !== ($itemSize ?? null)) {
                continue;
            }

            return $line;
        }

        return null;
    }
}
