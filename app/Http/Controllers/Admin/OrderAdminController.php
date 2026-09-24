<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesAdminUploads;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderAdminUpdate;
use Illuminate\Http\Request;

class OrderAdminController extends Controller
{
    public function index()
    {
        $orders = Order::latest()->paginate(15);

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load('items.product');

        return view('admin.orders.show', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $outcome = OrderAdminUpdate::apply(
            (int) $order->getKey(),
            $request->input('status'),
            $request->input('admin_notes'),
            $request->exists('status'),
        );

        if ($outcome === OrderAdminUpdate::STATUS_LOCKED) {
            return back()->withErrors([
                'status' => 'This order is awaiting payment reconciliation and its status cannot be changed here.',
            ])->withInput();
        }

        $message = $outcome === OrderAdminUpdate::NOTES_SAVED
            ? 'Order notes saved.'
            : 'Order updated.';

        return back()->with('success', $message);
    }
}
