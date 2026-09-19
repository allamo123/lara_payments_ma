<?php

namespace Ma\Payment\Interfaces;

interface SubscrptionableInterface
{
    public function subscription(): SubscriptionInterface;
}