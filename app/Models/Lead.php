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
        'subject',
        'message',
        'budget',
        'preferred_contact',
        'status',
        'admin_notes',
    ];

    public function typeLabel(): string
    {
        return match ($this->type) {
            'custom_order' => 'Custom Order',
            'contact' => 'Contact',
            'inquiry' => 'Inquiry',
            default => ucfirst($this->type),
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
