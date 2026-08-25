<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\StockAvailability;
use App\Support\CartGuard;
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
        $sizeLabel = is_string($request->input('size_label')) ? trim($request->input('size_label')) : null;
        if ($sizeLabel === '') {
            $sizeLabel = null;
        }

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

        $quantity = max(1, (int) $request->input('quantity', 1));
        $quantity = min($quantity, min($available, 99));
        $finishSlug = is_string($request->input('finish_slug')) ? $request->input('finish_slug') : null;
        // Never accept client-supplied commercial fields.
        unset($request['price'], $request['unit_price'], $request['total'], $request['discount'], $request['shipping']);

        if ($request->boolean('buy_now')) {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts('buy-now:'.$request->ip(), 8)) {
                return back()->with('error', 'Please wait a moment before trying again.');
            }
            \Illuminate\Support\Facades\RateLimiter::hit('buy-now:'.$request->ip(), 60);

            $this->cart->setBuyNow($product, $quantity, $finishSlug, $sizeLabel);

            if (! auth()->check()) {
                $request->session()->put('url.intended', route('checkout.index'));

                return redirect()->route('account.continue')
                    ->with('info', 'Sign in or create an account to continue securely to checkout.');
            }

            return redirect()->route('checkout.index');
        }

        $this->cart->add($product, $quantity, $finishSlug, $sizeLabel);

        return redirect()->route('cart.index')->with('success', 'Added to cart.');
    }

    public function update(Request $request, Product $product)
    {
        $cartLine = session('cart', [])[$product->id] ?? null;
        $sizeLabel = is_array($cartLine) ? ($cartLine['size_label'] ?? null) : null;

        if (! CartGuard::isEligible($product, is_string($sizeLabel) ? $sizeLabel : null)) {
            $this->cart->remove($product);

            return back()->with('error', CartGuard::checkoutEligibility($product, is_string($sizeLabel) ? $sizeLabel : null));
        }

        $available = StockAvailability::availableForProduct($product);
        $quantity = (int) $request->input('quantity', 1);
        $quantity = min(max(0, $quantity), min($available, 99));
        $this->cart->update($product, $quantity);

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Product $product)
    {
        $this->cart->remove($product);

        return back()->with('success', 'Item removed from cart.');
    }
}
