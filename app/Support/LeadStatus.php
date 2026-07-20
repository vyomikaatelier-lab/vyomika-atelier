<?php

namespace App\Support;

final class LeadStatus
{
  public const NEW = 'new';
  public const UNVERIFIED = 'unverified';
  public const VERIFIED = 'verified';
  public const QUALIFIED = 'qualified';
  public const MEASUREMENT_REQUIRED = 'measurement_required';
  public const QUOTATION_IN_PROGRESS = 'quotation_in_progress';
  public const QUOTATION_SENT = 'quotation_sent';
  public const FOLLOW_UP = 'follow_up';
  public const NEGOTIATION = 'negotiation';
  public const WON = 'won';
  public const LOST = 'lost';
  public const ON_HOLD = 'on_hold';
  public const DUPLICATE = 'duplicate';
  public const MARKETING_VENDOR = 'marketing_vendor';
  public const SPAM_SUSPECTED = 'spam_suspected';
  public const BLOCKED = 'blocked';

  /** @return list<string> */
  public static function workflow(): array
  {
    return [
      self::NEW,
      self::UNVERIFIED,
      self::VERIFIED,
      self::QUALIFIED,
      self::MEASUREMENT_REQUIRED,
      self::QUOTATION_IN_PROGRESS,
      self::QUOTATION_SENT,
      self::FOLLOW_UP,
      self::NEGOTIATION,
      self::WON,
      self::LOST,
      self::ON_HOLD,
      self::DUPLICATE,
      self::MARKETING_VENDOR,
      self::SPAM_SUSPECTED,
      self::BLOCKED,
    ];
  }

  public static function label(string $status): string
  {
    return match ($status) {
      self::NEW => 'New',
      self::UNVERIFIED => 'Unverified',
      self::VERIFIED => 'Verified',
      self::QUALIFIED => 'Qualified',
      self::MEASUREMENT_REQUIRED => 'Measurement Required',
      self::QUOTATION_IN_PROGRESS => 'Quotation In Progress',
      self::QUOTATION_SENT => 'Quotation Sent',
      self::FOLLOW_UP => 'Follow-up',
      self::NEGOTIATION => 'Negotiation',
      self::WON => 'Won',
      self::LOST => 'Lost',
      self::ON_HOLD => 'On Hold',
      self::DUPLICATE => 'Duplicate',
      self::MARKETING_VENDOR => 'Marketing or Vendor',
      self::SPAM_SUSPECTED => 'Spam Suspected',
      self::BLOCKED => 'Blocked',
      'contacted' => 'Contacted (legacy)',
      'quoted' => 'Quoted (legacy)',
      'converted' => 'Converted (legacy)',
      'closed' => 'Closed (legacy)',
      'under_review' => 'Under Review',
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      'more_info_required' => 'More Information Required',
      default => ucfirst(str_replace('_', ' ', $status)),
    };
  }

  public static function normalize(?string $status): string
  {
    $status = $status ?: self::NEW;

    return config('lead_qualification.legacy_status_map.' . $status, $status);
  }
}
