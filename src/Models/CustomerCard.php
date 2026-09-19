<?php

namespace Ma\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCard extends Model
{
    protected $fillable = [
        'customer_id',
        'gateway',
        'gateway_card_id',
        'token',
        'brand',
        'last_four',
        'expiry_month',
        'expiry_year',
        'cardholder_name',
        'metadata',
    ];

   public function customer(): BelongsTo
   {
        return $this->belongsTo(PaymentCustomer::class, 'customer_id');
   }
}
