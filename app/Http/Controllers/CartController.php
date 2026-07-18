<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Support\CartGuard;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index()
    {
        $items = $this->cart->all();
        $subtotal = $this->cart->subtotal();

        return view('cart.index', compact('items', 'subtotal'));
    }

    /**
     * Server-side gate: only active Shop products with purchase_mode=checkout
     * may enter the cart. Studio and Railings items are always rejected here,
     * even on a direct/forged POST that bypasses the storefront UI.
     */
    public function add(Request $request, Product $product)
    {
        if ($message = CartGuard::checkoutEligibility($product)) {
            return back()->with('error', $message);
        }

        if (! $product->inStock()) {
            return back()->with('error', CartGuard::MSG_INACTIVE);
        }

        $quantity = max(1, (int) $request->input('quantity', 1));
        $quantity = min($quantity, min($product->stock, 99));
        $this->cart->add($product, $quantity);

        if ($request->boolean('buy_now')) {
            return redirect()->route('cart.index');
        }

        return back()->with('success', 'Added to cart.');
    }

    public function update(Request $request, Product $product)
    {
        if (! CartGuard::isEligible($product)) {
            $this->cart->remove($product);

            return back()->with('error', CartGuard::checkoutEligibility($product));
        }

        $quantity = (int) $request->input('quantity', 1);
        $quantity = min(max(0, $quantity), min($product->stock, 99));
        $this->cart->update($product, $quantity);

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Product $product)
    {
        $this->cart->remove($product);

        return back()->with('success', 'Item removed from cart.');
    }
}
