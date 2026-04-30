<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruvAccount extends Model
{
    protected $fillable = [
        'user_id',
        'truv_account_id',
        'platform',
        'type',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
