<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteTestOrderRequest;
use App\Models\Order;
use App\Services\OrderTestDeletion;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

class OrderTestDeletionController extends Controller
{
    public function destroy(DeleteTestOrderRequest $request, Order $order, OrderTestDeletion $deletion): RedirectResponse
    {
        try {
            $result = $deletion->delete(
                $request->user(),
                (int) $order->getKey(),
                (string) $request->validated('order_number_confirmation'),
                (string) $request->validated('current_password'),
            );
        } catch (QueryException) {
            return back()->withErrors([
                'order' => 'This order cannot be deleted.',
            ]);
        }

        if ($result === OrderTestDeletion::DELETED) {
            return redirect()
                ->route('admin.orders.index')
                ->with('success', 'Test order deleted.');
        }

        return back()->withErrors([
            'order' => OrderTestDeletion::message($result),
        ]);
    }
}
