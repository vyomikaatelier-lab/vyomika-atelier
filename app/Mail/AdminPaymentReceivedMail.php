<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPaymentReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $subject = $this->order->hasDurableCapturedPaymentEvidence()
            ? "Payment received — {$this->order->order_number}"
            : "Payment not confirmed — {$this->order->order_number}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.admin-payment-received',
            with: [
                'order' => $this->order,
                'adminOrderUrl' => route('admin.orders.show', $this->order),
            ],
        );
    }
}
