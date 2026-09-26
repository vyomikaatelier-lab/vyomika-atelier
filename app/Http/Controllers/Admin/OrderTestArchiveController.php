<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmTestOrderArchiveRequest;
use App\Models\Order;
use App\Services\OrderTestArchive;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

class OrderTestArchiveController extends Controller
{
    public function store(ConfirmTestOrderArchiveRequest $request, Order $order, OrderTestArchive $archive): RedirectResponse
    {
        try {
            $result = $archive->archive(
                $request->user(),
                (int) $order->getKey(),
                (string) $request->validated('order_number_confirmation'),
                (string) $request->validated('current_password'),
            );
        } catch (QueryException) {
            return back()->withErrors([
                'order' => 'This order cannot be archived.',
            ]);
        }

        if ($result === OrderTestArchive::ARCHIVED) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('success', 'Test order archived.');
        }

        return back()->withErrors([
            'order' => OrderTestArchive::message($result),
        ]);
    }

    public function destroy(ConfirmTestOrderArchiveRequest $request, Order $order, OrderTestArchive $archive): RedirectResponse
    {
        try {
            $result = $archive->unarchive(
                $request->user(),
                (int) $order->getKey(),
                (string) $request->validated('order_number_confirmation'),
                (string) $request->validated('current_password'),
            );
        } catch (QueryException) {
            return back()->withErrors([
                'order' => 'This order cannot be unarchived.',
            ]);
        }

        if ($result === OrderTestArchive::UNARCHIVED) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('success', 'Test order unarchived.');
        }

        return back()->withErrors([
            'order' => OrderTestArchive::message($result, true),
        ]);
    }
}
