<?php

namespace Ma\Payment\Exceptions;

use RuntimeException;

class GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException extends RuntimeException
{
    public function __construct(
        public readonly int $localId,
        public readonly string $reference,
        public readonly string $gateway,
    ) {
        parent::__construct(
            "Local transaction with ID $localId not compatible with $gateway reference transaction ID. ($reference)"
        );
    }
}