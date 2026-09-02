<?php

namespace Ma\Payment\Exceptions;

use RuntimeException;

class TransactionCannotProcessException extends RuntimeException
{
    public function __construct(
        public readonly int $orderId
    ) {
        parent::__construct(
            "Transaction with order ID {$orderId} cannot be processed."
        );
    }
}