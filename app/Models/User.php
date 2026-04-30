<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;

    public const ROLE_RIDER = 'rider';

    public const ROLE_DRIVER = 'driver';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'firebase_uid',
        'role',
        'is_verified',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function linkedAccounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function driverTrips(): HasMany
    {
        return $this->hasMany(DriverTrip::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function userSetting(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function truvAccounts(): HasMany
    {
        return $this->hasMany(TruvAccount::class);
    }
}
