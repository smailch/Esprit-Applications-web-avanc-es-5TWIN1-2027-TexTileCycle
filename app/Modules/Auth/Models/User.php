<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Jenssegers\Mongodb\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    public const ROLE_CITOYEN = 'citoyen';

    public const ROLE_ATELIER = 'atelier';

    public const ROLE_ASSOCIATION = 'association';

    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_CITOYEN,
        self::ROLE_ATELIER,
        self::ROLE_ASSOCIATION,
        self::ROLE_ADMIN,
    ];

    protected $connection = 'mongodb';

    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'role' => self::ROLE_CITOYEN,
        'is_active' => true,
    ];

    public function setPasswordAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ATELIER => 'Atelier',
            self::ROLE_ASSOCIATION => 'Association',
            self::ROLE_ADMIN => 'Administrateur',
            default => 'Citoyen',
        };
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCitoyen(): bool
    {
        return $this->role === self::ROLE_CITOYEN;
    }

    public function canAccessBackOffice(): bool
    {
        return in_array($this->role, [
            self::ROLE_ATELIER,
            self::ROLE_ASSOCIATION,
            self::ROLE_ADMIN,
        ], true);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name ?? '')) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'TC';
    }
}
