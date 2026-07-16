<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'type',
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
        'admin_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'calculated_price' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /** @return list<string> */
    public static function generalStatuses(): array
    {
        return ['new', 'contacted', 'quoted', 'converted', 'closed'];
    }

    /** @return list<string> */
    public static function applicationStatuses(): array
    {
        return ['new', 'under_review', 'approved', 'rejected', 'more_info_required'];
    }

    /** @return list<string> */
    public static function quoteStatuses(): array
    {
        return ['new', 'under_review', 'quoted', 'converted', 'closed'];
    }

    public function allowedStatuses(): array
    {
        return match ($this->type) {
            'professional_application' => self::applicationStatuses(),
            'railing_quotation' => self::quoteStatuses(),
            default => self::generalStatuses(),
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'custom_order' => 'Custom Order',
            'service_inquiry' => 'Service Inquiry',
            'order_now' => 'Order Now',
            'professional_application' => 'Professional Application',
            'railing_quotation' => 'Railing Quotation',
            'contact' => 'Contact',
            'inquiry' => 'Inquiry',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'new' => 'New',
            'contacted' => 'Contacted',
            'quoted' => 'Quoted',
            'converted' => 'Converted',
            'closed' => 'Closed',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'more_info_required' => 'More Information Required',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function attachmentPath(): ?string
    {
        return is_array($this->metadata) ? ($this->metadata['drawing_path'] ?? null) : null;
    }

    public function attachmentFilename(): string
    {
        if (! is_array($this->metadata)) {
            return 'attachment';
        }

        return $this->metadata['drawing_filename'] ?? basename($this->metadata['drawing_path'] ?? 'attachment');
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachmentPath());
    }
}
