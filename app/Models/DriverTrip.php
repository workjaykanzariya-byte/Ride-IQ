<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverTrip extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'provider',
        'trip_id_external',
        'earnings',
        'distance',
        'duration',
        'trip_date',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
