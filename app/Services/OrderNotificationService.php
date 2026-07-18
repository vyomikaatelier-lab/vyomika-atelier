<?php

namespace App\Services;

use App\Mail\AdminNewOrderMail;
use App\Mail\AdminPaymentReceivedMail;
use App\Mail\OrderReceivedMail;
use App\Mail\PaymentSuccessfulMail;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderNotificationService
{
    public function sendOrderReceived(Order $order): bool
    {
        $order->loadMissing('items');

        $customerSent = $this->sendOnce(
            $order,
            'order_received_email_sent_at',
            $order->customer_email,
            new OrderReceivedMail($order),
            'order received customer'
        );

        $adminEmail = config('services.admin_email');
        if ($adminEmail) {
            $this->sendOnce(
                $order,
                'admin_order_notified_at',
                $adminEmail,
                new AdminNewOrderMail($order),
                'new order admin'
            );
        }

        return $customerSent;
    }

    public function sendPaymentConfirmed(Order $order): bool
    {
        $order->loadMissing('items');

        $customerSent = $this->sendOnce(
            $order,
            'payment_email_sent_at',
            $order->customer_email,
            new PaymentSuccessfulMail($order),
            'payment successful customer'
        );

        $adminEmail = config('services.admin_email');
        if ($adminEmail) {
            $this->sendOnce(
                $order,
                'admin_payment_notified_at',
                $adminEmail,
                new AdminPaymentReceivedMail($order),
                'payment received admin'
            );
        }

        return $customerSent;
    }

    private function sendOnce(Order $order, string $flagColumn, string $recipient, Mailable $mailable, string $context): bool
    {
        if ($order->{$flagColumn} !== null) {
            return true;
        }

        if (! $this->isMailConfigured()) {
            Log::warning("Order notification skipped ({$context}): mail is not configured.", [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return false;
        }

        if (! $this->claimSendFlag($order, $flagColumn)) {
            return true;
        }

        try {
            $this->dispatchMail($recipient, $mailable);

            return true;
        } catch (\Throwable $e) {
            $order->forceFill([$flagColumn => null])->save();

            Log::error("Order notification failed ({$context}).", [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function claimSendFlag(Order $order, string $flagColumn): bool
    {
        return (bool) Order::query()
            ->whereKey($order->id)
            ->whereNull($flagColumn)
            ->update([$flagColumn => now()]);
    }

    private function dispatchMail(string $recipient, Mailable $mailable): void
    {
        $queue = config('queue.default', 'sync');

        if ($queue !== 'sync' && $mailable instanceof \Illuminate\Contracts\Queue\ShouldQueue) {
            Mail::to($recipient)->queue($mailable);

            return;
        }

        Mail::to($recipient)->send($mailable);
    }

    public function isMailConfigured(): bool
    {
        if (blank(config('mail.from.address'))) {
            return false;
        }

        $mailer = config('mail.default', 'smtp');

        if (in_array($mailer, ['array', 'log', 'failover'], true)) {
            return $mailer === 'array';
        }

        $host = config("mail.mailers.{$mailer}.host");

        return filled($host) || $mailer === 'sendmail';
    }
}
