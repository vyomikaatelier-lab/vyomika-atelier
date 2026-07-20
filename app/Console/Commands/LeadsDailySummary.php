<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Support\LeadPriority;
use App\Support\LeadProtectionStatus;
use App\Support\LeadStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class LeadsDailySummary extends Command
{
  protected $signature = 'leads:daily-summary';

  protected $description = 'Send daily lead pipeline summary to admin';

  public function handle(): int
  {
    $recipient = config('services.admin_email');
    if (! $recipient) {
      $this->warn('ADMIN_EMAIL not configured.');

      return self::FAILURE;
    }

    $salesQuery = fn () => Lead::query()->where('enquiry_type', '!=', 'vendor_marketing');

    $stats = [
      'new' => $salesQuery()->where('status', LeadStatus::NEW)->count(),
      'verified' => $salesQuery()->where('status', LeadStatus::VERIFIED)->count(),
      'hot' => $salesQuery()->where('priority', LeadPriority::HOT)->count(),
      'qualified' => $salesQuery()->where('status', LeadStatus::QUALIFIED)->count(),
      'duplicates' => Lead::where('protection_status', LeadProtectionStatus::DUPLICATE)->count(),
      'vendor' => Lead::where('enquiry_type', 'vendor_marketing')->where('status', LeadStatus::NEW)->count(),
      'spam' => Lead::where('protection_status', LeadProtectionStatus::SPAM_SUSPECTED)->count(),
      'overdue_followups' => $salesQuery()->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now())->count(),
      'quotation_sent' => $salesQuery()->where('status', LeadStatus::QUOTATION_SENT)->count(),
      'won' => $salesQuery()->where('status', LeadStatus::WON)->count(),
      'lost' => $salesQuery()->where('status', LeadStatus::LOST)->count(),
    ];

    $body = "Vyomika Atelier — Daily Lead Summary (" . now()->format('d M Y') . ")\n\n";
    foreach ($stats as $key => $count) {
      $body .= ucwords(str_replace('_', ' ', $key)) . ": {$count}\n";
    }

    try {
      Mail::raw($body, fn ($message) => $message->to($recipient)->subject('Daily Lead Summary — Vyomika Atelier'));
      $this->info('Daily summary sent to ' . $recipient);
    } catch (\Throwable $e) {
      $this->error('Failed to send summary: ' . $e->getMessage());

      return self::FAILURE;
    }

    return self::SUCCESS;
  }
}
