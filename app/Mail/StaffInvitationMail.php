<?php

namespace App\Mail;

use App\Models\StaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Optional future delivery channel. The default invitation flow is a one-time
 * Owner-visible secure link and does not send mail automatically.
 */
class StaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public StaffInvitation $invitation,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Set up your Vyomika Atelier staff account');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.admin.staff-invitation');
    }
}
