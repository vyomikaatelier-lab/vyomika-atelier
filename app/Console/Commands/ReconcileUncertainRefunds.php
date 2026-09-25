<?php

namespace App\Console\Commands;

use App\Models\OrderRefund;
use App\Services\OrderRefundService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileUncertainRefunds extends Command
{
    protected $signature = 'orders:reconcile-refunds';

    protected $description = 'Reconcile open refunds from Razorpay. Does not create a refund or a new idempotency key.';

    public function handle(OrderRefundService $refunds): int
    {
        $updated = 0;
        $unchanged = 0;
        $limited = 0;

        OrderRefund::query()
            ->whereIn('status', OrderRefund::OPEN_STATUSES)
            ->orderBy('id')
            ->each(function (OrderRefund $refund) use ($refunds, &$updated, &$unchanged, &$limited) {
                try {
                    $result = $refunds->reconcileFromGateway($refund);
                } catch (Throwable) {
                    $unchanged++;

                    return;
                }

                if ($result === 'updated') {
                    $updated++;

                    return;
                }

                if ($result === 'scan_limited') {
                    $limited++;

                    return;
                }

                $unchanged++;
            });

        $this->info("Reconciled {$updated} refund(s); left {$unchanged} unchanged.");

        if ($limited > 0) {
            $this->warn('Recovery scan limit reached.');
        }

        return self::SUCCESS;
    }
}
