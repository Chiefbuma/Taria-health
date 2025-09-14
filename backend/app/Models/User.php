<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    const ROLE_ADMIN = 'admin';
    const ROLE_USER = 'user';
    const ROLE_NAVIGATOR = 'navigator';
    const ROLE_PAYER = 'payer';
    const ROLE_GUEST = 'guest';
    const ROLE_CLAIMS = 'claims';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'payer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Check if user is navigator
     */
    public function isNavigator(): bool
    {
        return $this->hasRole(self::ROLE_NAVIGATOR);
    }

    /**
     * Check if user is payer
     */
    public function isPayer(): bool
    {
        return $this->hasRole(self::ROLE_PAYER);
    }

    /**
     * Check if user is guest
     */
    public function isGuest(): bool
    {
        return $this->hasRole(self::ROLE_GUEST);
    }

    /**
     * Check if user is claims
     */
    public function isClaims(): bool
    {
        return $this->hasRole(self::ROLE_CLAIMS);
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get all available roles
     */
    public static function getAvailableRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_USER,
            self::ROLE_NAVIGATOR,
            self::ROLE_PAYER,
            self::ROLE_GUEST,
            self::ROLE_CLAIMS,
        ];
    }

    /**
     * Get the payer associated with the user.
     */
    public function payer()
    {
        return $this->belongsTo(Payer::class);
    }
}
