<?php

namespace Ma\Payment\ValueObjects;

use InvalidArgumentException;

final class UserEmail
{
    public function __construct(private string $userEmail)
    {
        if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $userEmail)) {
            throw new InvalidArgumentException('Invalid email');
        }   
    }

    public function value(): string
    {
        return $this->userEmail;
    }
}