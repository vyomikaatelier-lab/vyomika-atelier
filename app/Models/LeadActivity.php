<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
  protected $fillable = [
    'lead_id',
    'user_id',
    'activity_type',
    'body',
    'metadata',
  ];

  protected function casts(): array
  {
    return [
      'metadata' => 'array',
    ];
  }

  public function lead(): BelongsTo
  {
    return $this->belongsTo(Lead::class);
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function typeLabel(): string
  {
    return match ($this->activity_type) {
      'note' => 'Internal Note',
      'status_change' => 'Status Change',
      'assignment' => 'Assignment',
      'follow_up_scheduled' => 'Follow-up Scheduled',
      'contact' => 'Contact',
      'merge' => 'Duplicate Merge',
      'block' => 'Blocked',
      'restore' => 'Restored',
      'score_recalc' => 'Score Recalculated',
      default => ucfirst(str_replace('_', ' ', $this->activity_type)),
    };
  }
}
