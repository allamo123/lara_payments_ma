<?php

namespace Ma\Payment\Interfaces;

interface ViewableCheckoutGatewayInterface
{
    public function paymentView(array $data = []): mixed;
}