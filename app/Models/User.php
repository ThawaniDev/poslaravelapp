<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Base User model.
 * The main User model for auth lives at App\Domain\Auth\Models\User.
 * This is kept for Laravel compatibility (auth config default).
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'organization_id', 'email', 'phone', 'password_hash',
        'full_name', 'full_name_ar', 'role', 'locale', 'theme',
        'is_active', 'email_verified_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
