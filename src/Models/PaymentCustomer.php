<?php

namespace Ma\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentCustomer extends Model
{
    protected $fillable = [
        'gateway',
        'gateway_customer_id',
        'user_id',
        'name',
        'email',
        'phone',
    ];

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'customer_id');
    }
}
