<?php

namespace Ma\Payment\Exceptions;

use RuntimeException;

class MissingPaymentInfoException extends RuntimeException
{
    public function __construct(string $missing_payment_parameter, string $payment_provider)
    {
        parent::__construct($missing_payment_parameter . ' is required to use ' . $payment_provider);
    }
}