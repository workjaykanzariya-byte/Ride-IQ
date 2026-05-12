<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoWebhookLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = ['event_type','webhook_data','status','created_at'];

    protected function casts(): array
    {
        return ['webhook_data' => 'array', 'created_at' => 'datetime'];
    }
}
