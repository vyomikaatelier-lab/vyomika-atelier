<?php

namespace App\Support;

final class LeadPriority
{
  public const HOT = 'hot';
  public const HIGH = 'high';
  public const MEDIUM = 'medium';
  public const LOW = 'low';

  public static function fromScore(int $score): string
  {
    $bands = config('lead_qualification.score_bands', []);

    return match (true) {
      $score >= ($bands['hot'] ?? 70) => self::HOT,
      $score >= ($bands['qualified'] ?? 40) => self::HIGH,
      $score >= ($bands['needs_verification'] ?? 20) => self::MEDIUM,
      default => self::LOW,
    };
  }

  public static function label(?string $priority): string
  {
    return match ($priority) {
      self::HOT => 'Hot',
      self::HIGH => 'High',
      self::MEDIUM => 'Medium',
      self::LOW => 'Low',
      default => '—',
    };
  }

  public static function scoreBandLabel(int $score): string
  {
    $bands = config('lead_qualification.score_bands', []);

    return match (true) {
      $score >= ($bands['hot'] ?? 70) => 'Hot Lead',
      $score >= ($bands['qualified'] ?? 40) => 'Qualified',
      $score >= ($bands['needs_verification'] ?? 20) => 'Needs Verification',
      $score >= ($bands['low_priority'] ?? 0) => 'Low Priority',
      default => 'Spam Review',
    };
  }
}
