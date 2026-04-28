<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPlatformEarning extends Model
{
    protected $table = 'driver_platform_earnings';

    protected $fillable = [
        'user_id',
        'platform_name',
        'total_earnings',
        'currency',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'total_earnings' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
