<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderAdminUpdate;
use App\Services\RazorpayRefundRequest;
use App\Support\AdminRole;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderAdminController extends Controller
{
    public function index()
    {
        $orders = Order::latest()->paginate(15);

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'refunds.lines']);

        $idempotencyKey = null;
        if ($requestUser = auth()->user()) {
            if ($requestUser->hasAdminPermission(AdminRole::ORDERS_REFUND) && $order->canOfferRefund()) {
                $sessionKey = 'order_refund_idempotency.'.$order->getKey();
                $idempotencyKey = session($sessionKey);
                if (! is_string($idempotencyKey) || ! RazorpayRefundRequest::validIdempotencyKey($idempotencyKey)) {
                    $idempotencyKey = (string) Str::uuid();
                    session([$sessionKey => $idempotencyKey]);
                }
            }
        }

        return view('admin.orders.show', compact('order', 'idempotencyKey'));
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

        if ($outcome === OrderAdminUpdate::REFUND_REQUIRED) {
            return back()->withErrors([
                'status' => 'Paid orders are cancelled by issuing a refund. The status was not changed.',
            ])->withInput();
        }

        if ($outcome === OrderAdminUpdate::CAPTURE_REQUIRED) {
            return back()->withErrors([
                'status' => 'This order cannot be marked paid, processing, shipped, or delivered until payment is captured. The status was not changed.',
            ])->withInput();
        }

        if ($outcome === OrderAdminUpdate::INVESTIGATION_REQUIRED) {
            return back()->withErrors([
                'status' => 'This order has stock, payment, or refund evidence and cannot be changed here. It needs manual investigation. The status was not changed.',
            ])->withInput();
        }

        $message = $outcome === OrderAdminUpdate::NOTES_SAVED
            ? 'Order notes saved.'
            : 'Order updated.';

        return back()->with('success', $message);
    }
}
