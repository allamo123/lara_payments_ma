<?php

namespace Ma\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'gateway',
        'order_id',
        'customer_id',
        'gateway_reference',
        'minor_amount',
        'remain_minor_amount',
        'currency',
        'subscription_id',
        'subscription_transaction_type',
        'status',
        'source',
        'source_subtype',
        'meta_data'
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentTransaction $transaction) {
            $transaction->remain_minor_amount ??= $transaction->minor_amount;
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PaymentCustomer::class, 'customer_id');
    }

    public function refundedPayments()
    {
        return $this->hasMany(RefundedPaymentTransaction::class, 'parent_transaction', 'gateway_reference');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }
}
