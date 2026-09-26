<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessfulMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $subject = $this->order->hasDurableCapturedPaymentEvidence()
            ? "Payment confirmed — {$this->order->order_number}"
            : "Payment not confirmed — {$this->order->order_number}";

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.payment-successful',
            with: [
                'order' => $this->order,
                'supportEmail' => config('site.brand.email', 'namaste@vyomikaatelier.com'),
            ],
        );
    }
}
