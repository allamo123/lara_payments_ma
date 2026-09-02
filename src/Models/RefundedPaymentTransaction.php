<?php

namespace Ma\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class RefundedPaymentTransaction extends Model
{
    protected $fillable = [
        'parent_transaction',
        'order_id',
        'transaction_id',
        'minor_amount',
        'currency',
        'refund_type',
        'status',
        'meta_data'
    ];

    public function parentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'parent_transaction', 'gateway_reference');
    }
}
