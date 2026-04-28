<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverTruvAccount extends Model
{
    protected $table = 'driver_truv_accounts';

    protected $fillable = [
        'user_id',
        'truv_user_id',
        'link_id',
        'access_token',
        'verification_status',
        'connected_at',
        'verified_at',
        'last_report',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_report' => 'array',
            'connected_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
