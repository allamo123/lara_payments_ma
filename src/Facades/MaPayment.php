<?php

namespace Ma\Payment\Facades;

use Illuminate\Support\Facades\Facade;
use Ma\Payment\PaymentGatewayManager;

class MaPayment extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return PaymentGatewayManager::class;
    }
}