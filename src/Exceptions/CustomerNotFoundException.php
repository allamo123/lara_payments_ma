<?php

namespace Ma\Payment\Exceptions;

use RuntimeException;

class CustomerNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly int $userId
    ) {
        parent::__construct(
            "Customer that has user ID {$userId} not found."
        );
    }
}