<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminRolePermissionAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'actor_staff_id',
        'admin_role',
        'permission',
        'previous_enabled',
        'new_enabled',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_enabled' => 'boolean',
            'new_enabled' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
