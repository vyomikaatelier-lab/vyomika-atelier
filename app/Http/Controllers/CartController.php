<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
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

    public function add(Request $request, Product $product)
    {
        if (! $product->is_active || ! $product->inStock()) {
            return back()->with('error', 'This product is currently unavailable.');
        }

        $quantity = max(1, (int) $request->input('quantity', 1));
        $this->cart->add($product, $quantity);

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.index');
        }

        return back()->with('success', 'Added to cart.');
    }

    public function update(Request $request, Product $product)
    {
        $quantity = (int) $request->input('quantity', 1);
        $this->cart->update($product, $quantity);

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Product $product)
    {
        $this->cart->remove($product);

        return back()->with('success', 'Item removed from cart.');
    }
}
