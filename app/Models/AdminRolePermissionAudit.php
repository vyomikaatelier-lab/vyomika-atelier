<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

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

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Permission audit records cannot be changed.');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Permission audit records cannot be changed.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Permission audit records cannot be deleted.');
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('Permission audit records cannot be deleted.');
    }
}
