<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    protected $fillable = [
        'user_id',
        'pickup_location',
        'drop_location',
        'pickup_lat',
        'pickup_lng',
        'drop_lat',
        'drop_lng',
        'status',
        'external_ride_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rideOptions(): HasMany
    {
        return $this->hasMany(RideOption::class);
    }
}
