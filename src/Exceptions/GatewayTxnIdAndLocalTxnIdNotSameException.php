<?php

namespace Ma\Payment\Exceptions;

use RuntimeException;

class GatewayTxnIdAndLocalTxnIdNotSameException extends RuntimeException
{
    public function __construct(
        public readonly int $txnId,
        public readonly string $gateway
    ) {
        parent::__construct(
            "Local transaction with order ID {$txnId} not compatible with {$gateway} order {$gateway} ."
        );
    }
}