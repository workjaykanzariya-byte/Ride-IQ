<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverEarning extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'date',
        'total_earnings',
        'total_trips',
        'total_hours',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
