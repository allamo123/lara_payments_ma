<?php

namespace Ma\Payment\Exceptions;

use RuntimeException;

class TransactionAlreadyProccessedException extends RuntimeException
{
    public function __construct(
        public readonly int $orderId
    ) {
        parent::__construct(
            "Transaction with order ID {$orderId} already proccessed."
        );
    }
}