<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderAdminUpdate
{
    public const STATUS_LOCKED = 'status_locked';

    public const NOTES_SAVED = 'notes_saved';

    public const UPDATED = 'updated';

    /**
     * Decide the write only after the order row is locked.
     * A stale form cannot overwrite reconciliation evidence.
     *
     * @return self::STATUS_LOCKED|self::NOTES_SAVED|self::UPDATED
     */
    public static function apply(int $orderId, mixed $status, mixed $adminNotes, bool $statusFieldPresent): string
    {
        $notes = Validator::make(
            ['admin_notes' => $adminNotes],
            ['admin_notes' => 'nullable|string|max:5000'],
        )->validate();

        return DB::transaction(function () use ($orderId, $status, $notes, $statusFieldPresent) {
            $locked = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            if ($locked->needsPaymentReview()) {
                if ($statusFieldPresent && (string) $status !== $locked->status) {
                    return self::STATUS_LOCKED;
                }

                $locked->forceFill([
                    'admin_notes' => $notes['admin_notes'] ?? null,
                ])->save();

                return self::NOTES_SAVED;
            }

            $statusValidated = Validator::make(
                ['status' => $status],
                ['status' => 'required|in:pending,paid,processing,shipped,delivered,cancelled'],
            )->validate();

            $locked->forceFill([
                'status' => $statusValidated['status'],
                'admin_notes' => $notes['admin_notes'] ?? null,
            ])->save();

            return self::UPDATED;
        });
    }
}
