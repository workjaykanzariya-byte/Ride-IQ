<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverEarning extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'provider',
        'date',
        'statement_date',
        'gross_income',
        'net_income',
        'pay_frequency',
        'currency',
        'raw_json',
        'total_earnings',
        'total_trips',
        'total_hours',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'statement_date' => 'date',
            'gross_income' => 'decimal:2',
            'net_income' => 'decimal:2',
            'raw_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
