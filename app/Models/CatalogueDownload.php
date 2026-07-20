<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueDownload extends Model
{
  protected $fillable = [
    'lead_id',
    'email',
    'phone',
    'profession',
    'city',
    'download_token',
    'expires_at',
    'downloaded_at',
    'ip_fingerprint',
  ];

  protected function casts(): array
  {
    return [
      'expires_at' => 'datetime',
      'downloaded_at' => 'datetime',
    ];
  }

  public function lead(): BelongsTo
  {
    return $this->belongsTo(Lead::class);
  }

  public function isExpired(): bool
  {
    return $this->expires_at && $this->expires_at->isPast();
  }
}
