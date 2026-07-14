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
    ];

    protected function casts(): array
    {
        return [
            'calculated_price' => 'decimal:2',
        ];
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'custom_order' => 'Custom Order',
            'service_inquiry' => 'Service Inquiry',
            'order_now' => 'Order Now',
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
            default => ucfirst($this->status),
        };
    }
}
