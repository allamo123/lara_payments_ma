<?php

namespace Ma\Payment\ValueObjects;

use InvalidArgumentException;

final class Money
{
    public function __construct(private float $amount)
    {
        if($amount <= 0)
        {
            throw new InvalidArgumentException('Amount not valid it must be > 0');
        }
    }

    public function value(): float
    {
        return $this->amount;
    }

    public function add(float $amount): float
    {
        return $this->amount + $this->value();
    }

    public function toPounds(): float
    {
        return number_format($this->value()/100);
    }
    public function toCents(): float
    {
        return $this->value() * 100;
    }
}