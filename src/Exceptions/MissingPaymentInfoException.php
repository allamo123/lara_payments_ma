<?php

<<<<<<< HEAD
namespace Ma\Payment\Exceptions;

use RuntimeException;

class MissingPaymentInfoException extends RuntimeException
{
    public function __construct(string $missing_payment_parameter, string $payment_provider)
=======
namespace Ma\Payments\Exceptions;

class MissingPaymentInfoException extends \Exception
{
    public function __construct($missing_payment_parameter, $payment_provider)
>>>>>>> f8bdc302023ca20d19961ae691112a478ea6409f
    {
        parent::__construct($missing_payment_parameter . ' is required to use ' . $payment_provider);
    }
}