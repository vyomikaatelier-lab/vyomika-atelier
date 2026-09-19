<?php

namespace App\Services;

use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use Illuminate\Support\Facades\Mail;

/**
 * Synchronous invitation mail delivery. Must not be queued: the Owner flow
 * needs a definite send/fail result in the same request.
 */
class StaffInvitationMailer
{
    public function send(StaffInvitation $invitation, string $acceptUrl): void
    {
        Mail::to($invitation->email)->send(new StaffInvitationMail($invitation, $acceptUrl));
    }
}
