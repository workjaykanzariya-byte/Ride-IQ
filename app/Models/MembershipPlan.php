<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'zoho_plan_code','name','description','price','interval_count','interval_unit','currency_code','status','raw_response_json',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'raw_response_json' => 'array'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }
}
