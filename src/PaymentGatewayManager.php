<?php 

namespace Ma\Payment;

use Ma\Payment\Factories\PaymentGatewayFactory;

class PaymentGatewayManager
{ 
    public function __construct(private PaymentGatewayFactory $PaymentGatewayFactory){}

    public function driver(string $driver)
    {
        return $this->PaymentGatewayFactory->create($driver);
    }
}