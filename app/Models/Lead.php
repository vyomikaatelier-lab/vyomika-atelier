<?php

namespace App\Models;

use App\Support\EnquiryType;
use App\Support\LeadPriority;
use App\Support\LeadProtectionStatus;
use App\Support\LeadStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'type',
        'enquiry_type',
        'enquiry_intent',
        'service_slug',
        'design_slug',
        'subject',
        'message',
        'budget',
        'calculated_price',
        'dimensions',
        'unit_type',
        'preferred_contact',
        'status',
        'protection_status',
        'risk_score',
        'risk_reasons',
        'lead_score',
        'lead_score_reasons',
        'priority',
        'assigned_to',
        'next_follow_up_at',
        'last_contacted_at',
        'expected_order_value',
        'lost_reason',
        'ip_fingerprint',
        'duplicate_of_id',
        'duplicate_count',
        'whatsapp_verified',
        'notifications_suppressed',
        'submission_duration_ms',
        'restored_at',
        'false_positive_at',
        'admin_notes',
        'internal_notes',
        'metadata',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer',
        'landing_page',
        'first_touch_source',
        'last_touch_source',
        'device_type',
    ];

    protected function casts(): array
    {
        return [
            'calculated_price' => 'decimal:2',
            'expected_order_value' => 'decimal:2',
            'metadata' => 'array',
            'risk_reasons' => 'array',
            'lead_score_reasons' => 'array',
            'internal_notes' => 'array',
            'notifications_suppressed' => 'boolean',
            'whatsapp_verified' => 'boolean',
            'restored_at' => 'datetime',
            'false_positive_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
        ];
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    public function blockedIdentities(): HasMany
    {
        return $this->hasMany(BlockedIdentity::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    public function catalogueDownloads(): HasMany
    {
        return $this->hasMany(CatalogueDownload::class);
    }

    /** @return list<string> */
    public static function workflowStatuses(): array
    {
        return LeadStatus::workflow();
    }

    /** @return list<string> */
    public static function generalStatuses(): array
    {
        return self::workflowStatuses();
    }

    /** @return list<string> */
    public static function applicationStatuses(): array
    {
        return self::workflowStatuses();
    }

    /** @return list<string> */
    public static function quoteStatuses(): array
    {
        return self::workflowStatuses();
    }

    public function allowedStatuses(): array
    {
        return self::workflowStatuses();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'custom_order' => 'Custom Order',
            'service_inquiry' => 'Service Inquiry',
            'order_now' => 'Order Now',
            'professional_application' => 'Professional Application',
            'railing_quotation' => 'Railing Quotation',
            'dealer_application' => 'Dealer Application',
            'catalogue_request' => 'Catalogue Request',
            'vendor_proposal' => 'Vendor Proposal',
            'contact' => 'Contact',
            'inquiry' => 'Inquiry',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function enquiryTypeLabel(): string
    {
        return EnquiryType::label($this->enquiry_type);
    }

    public function statusLabel(): string
    {
        return LeadStatus::label((string) $this->status);
    }

    public function priorityLabel(): string
    {
        return LeadPriority::label($this->priority);
    }

    public function scoreBandLabel(): string
    {
        return LeadPriority::scoreBandLabel((int) $this->lead_score);
    }

    public function attachmentPath(): ?string
    {
        if (! is_array($this->metadata)) {
            return null;
        }

        return $this->metadata['drawing_path'] ?? $this->metadata['reference_upload'] ?? null;
    }

    public function attachmentFilename(): string
    {
        if (! is_array($this->metadata)) {
            return 'attachment';
        }

        return $this->metadata['drawing_filename']
            ?? $this->metadata['reference_filename']
            ?? basename($this->metadata['drawing_path'] ?? $this->metadata['reference_upload'] ?? 'attachment');
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachmentPath());
    }

    public function protectionStatusLabel(): string
    {
        return LeadProtectionStatus::label((string) $this->protection_status);
    }

    public function enquiryIntentLabel(): ?string
    {
        if (! $this->enquiry_intent) {
            return null;
        }

        return config('form_protection.enquiry_intents.' . $this->enquiry_intent);
    }

    public function isSalesLead(): bool
    {
        return EnquiryType::isSalesQueue($this->enquiry_type);
    }

    public function appendInternalNote(string $note, ?int $userId = null): void
    {
        $notes = is_array($this->internal_notes) ? $this->internal_notes : [];
        $notes[] = [
            'at' => now()->toIso8601String(),
            'user_id' => $userId,
            'body' => $note,
        ];
        $this->update(['internal_notes' => $notes]);
    }

    /** @return list<string> */
    public static function protectionStatuses(): array
    {
        return LeadProtectionStatus::all();
    }
}
