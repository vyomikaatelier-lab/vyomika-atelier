<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\StockAvailability;
use App\Support\CartGuard;
use App\Support\FinishSwatches;
use App\Support\SafeInternalUrl;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index()
    {
        $items = $this->cart->all();
        $subtotal = $this->cart->subtotal();
        $pendingOrder = null;

        $pendingId = session(\App\Support\OrderAccess::SESSION_KEY);
        if ($pendingId) {
            $pendingOrder = \App\Models\Order::query()->find($pendingId);
            if ($pendingOrder && ($pendingOrder->status !== 'pending' || $pendingOrder->isExpired())) {
                $pendingOrder = null;
            }
        }

        return view('cart.index', compact('items', 'subtotal', 'pendingOrder'));
    }

    /**
     * Server-side gate: only active Shop products with purchase_mode=checkout
     * may enter the cart. Studio and Railings items are always rejected here,
     * even on a direct/forged POST that bypasses the storefront UI.
     */
    public function add(Request $request, Product $product)
    {
        $sizeLabel = $this->stringInput($request, 'size_label');
        $finishSlug = $this->stringInput($request, 'finish_slug');

        if ($message = CartGuard::checkoutEligibility($product, $sizeLabel)) {
            return back()->with('error', $message);
        }

        if (! $product->inStock()) {
            return back()->with('error', CartGuard::MSG_INACTIVE);
        }

        $available = StockAvailability::availableForProduct($product);
        if ($available < 1) {
            return back()->with('error', CartGuard::MSG_INACTIVE);
        }

        if (filled($finishSlug) && FinishSwatches::findBySlug($finishSlug) === null) {
            return back()->with('error', CartGuard::MSG_INVALID_FINISH);
        }

        if ($this->cart->validatedVariant($product, $sizeLabel, $finishSlug, ! filled($finishSlug)) === null) {
            return back()->with('error', filled($finishSlug) ? CartGuard::MSG_INVALID_FINISH : CartGuard::MSG_VARIANT_REQUIRED);
        }

        $quantity = max(1, (int) $request->input('quantity', 1));
        // Never accept client-supplied commercial fields.
        unset($request['price'], $request['unit_price'], $request['total'], $request['discount'], $request['shipping']);

        if ($request->boolean('buy_now')) {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts('buy-now:'.$request->ip(), 8)) {
                return back()->with('error', 'Please wait a moment before trying again.');
            }
            \Illuminate\Support\Facades\RateLimiter::hit('buy-now:'.$request->ip(), 60);

            $this->cart->setBuyNow($product, min($quantity, min($available, 99)), $finishSlug, $sizeLabel);

            if (! auth()->check()) {
                $request->session()->put('url.intended', route('checkout.index'));

                return redirect()->route('account.continue')
                    ->with('info', 'Sign in or create an account to continue securely to checkout.');
            }

            return redirect()->route('checkout.index');
        }

        $result = $this->cart->add($product, $quantity, $finishSlug, $sizeLabel);

        if ($result['quantity'] < 1) {
            return back()->with('error', 'This item does not have enough stock to add to your bag.');
        }

        $redirect = $this->redirectToOriginWithDrawer($request, $product)
            ->with('success', 'Added to cart.');

        if ($result['clamped']) {
            $redirect->with('info', 'Quantity was limited to available stock.');
        }

        return $redirect;
    }

    public function update(Request $request, Product $product)
    {
        $sizeLabel = $this->stringInput($request, 'size_label');
        $finishSlug = $this->stringInput($request, 'finish_slug');

        if (! CartGuard::isEligible($product, $sizeLabel)) {
            $this->cart->remove($product, $sizeLabel, $finishSlug);

            return back()->with('error', CartGuard::checkoutEligibility($product, $sizeLabel) ?? CartGuard::MSG_INACTIVE);
        }

        $quantity = (int) $request->input('quantity', 1);
        $updated = $this->cart->update($product, $quantity, $sizeLabel, $finishSlug);

        if (! $updated) {
            return back()->with('error', 'That item could not be updated. Please try again from your cart.');
        }

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, Product $product)
    {
        $removed = $this->cart->remove(
            $product,
            $this->stringInput($request, 'size_label'),
            $this->stringInput($request, 'finish_slug'),
        );

        if (! $removed) {
            return back()->with('error', 'That item could not be removed. Please try again from your cart.');
        }

        return back()->with('success', 'Item removed from cart.');
    }

    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function redirectToOriginWithDrawer(Request $request, Product $product)
    {
        $fallback = route('shop.show', $product->slug);
        $origin = $request->headers->get('referer');
        $url = $fallback;

        if (is_string($origin) && SafeInternalUrl::isSafe($origin)) {
            $url = $origin;
        }

        return redirect()->to($this->withOpenCartDrawer($url));
    }

    private function withOpenCartDrawer(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url.'#am-cart-drawer';
        }

        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $query);
        $query['cart'] = 'open';
        $queryString = http_build_query($query);

        $prefix = '';
        if (! empty($parts['scheme']) && ! empty($parts['host'])) {
            $prefix = $parts['scheme'].'://'.$parts['host'];
            if (! empty($parts['port'])) {
                $prefix .= ':'.$parts['port'];
            }
        }

        return $prefix.$path.($queryString !== '' ? '?'.$queryString : '').'#am-cart-drawer';
    }
}
