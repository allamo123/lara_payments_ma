<?php

namespace Ma\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionWebhookEvent extends Model
{
    protected $fillable =[
        'gateway',
        'event_id',
        'event_type',
        'payload',
        'processed_at',
        'failure_reason',
    ];
}
