<?php

namespace Ma\Payments\Facades;

use Illuminate\Support\Facades\Facade;

class MaPaymentsFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ma_payments';
    }
}