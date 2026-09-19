<?php

namespace Ma\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'gateway',
        'gateway_plan_id',
        'name',
        'minor_amount',
        'billing_interval_count',
        'billing_cycles',
        'is_active',
        'metadata',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
