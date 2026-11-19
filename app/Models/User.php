<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerTier;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'document_type',
    'document_number',
    'default_address',
    'district',
    'city',
    'last_login_at',
])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'customer_tier' => CustomerTier::class,
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function wholesaleProfile(): HasOne
    {
        return $this->hasOne(WholesaleProfile::class);
    }

    public function approvedWholesaleProfiles(): HasMany
    {
        return $this->hasMany(WholesaleProfile::class, 'approved_by');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function inspectedReturns(): HasMany
    {
        return $this->hasMany(OrderReturn::class, 'inspector_id');
    }

    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'verified_by');
    }

    public function securityLogs(): HasMany
    {
        return $this->hasMany(SecurityLog::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function abandonedCarts(): HasMany
    {
        return $this->hasMany(AbandonedCart::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function effectiveTier(): CustomerTier
    {
        $profile = $this->wholesaleProfile;

        if ($profile !== null && $profile->status === 'approved' && $profile->approved_tier !== null) {
            return $profile->approved_tier;
        }

        return $this->customer_tier ?? CustomerTier::Minorista;
    }

    public function hasApprovedWholesale(): bool
    {
        return $this->wholesaleProfile !== null && $this->wholesaleProfile->status === 'approved';
    }
}
