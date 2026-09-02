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

    public function pounds(): float
    {
        return $this->minor_amount/100;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PaymentCustomer::class, 'customer_id');
    }

    public function refundedPayments()
    {
        return $this->hasMany(RefundedPaymentTransaction::class, 'parent_transaction', 'gateway_reference');
    }
}
