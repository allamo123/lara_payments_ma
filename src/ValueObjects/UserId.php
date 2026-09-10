<?php

namespace Ma\Payment\ValueObjects;

use InvalidArgumentException;

final class UserId
{
    public function __construct(private int $userId)
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID cannot less than zero");
            
        }
    }

    public function value(): int
    {
        return $this->userId;
    }
}