<?php

namespace Ma\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'gateway',
        'gateway_subscription_id',
        'customer_id',
        'plan_id',
        'next_billing',
        'starts_at',
        'ends_at',
        'reminder_date',
        'suspended_at',
        'resumed_at',
        'reactivated_at',
        'status',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PaymentCustomer::class, 'customer_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'subscription_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
