<?php

namespace Ma\Payment\Exceptions;

use RuntimeException;

class RefundTransactionNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly int $refundId
    ) {
        parent::__construct(
            "Refund transaction ID {$refundId} not found."
        );
    }
}