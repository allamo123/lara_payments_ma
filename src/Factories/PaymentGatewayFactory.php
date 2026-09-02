<?php

namespace Ma\Payment\Factories;

use Ma\Payment\Interfaces\PaymentGatewayInterface;
use Exception;

class PaymentGatewayFactory
{
    public function create(string $driver): PaymentGatewayInterface
    {
        $driver = strtolower($driver);

        $drivers = config("ma-drivers");
        
        if (!array_key_exists($driver, $drivers)) {
            throw new Exception("Payment gateway [driver] does not exist");
        }

        $class = $drivers[$driver];

        if(!is_subclass_of($class, PaymentGatewayInterface::class)) {
            throw new Exception("[$class] must implement PaymentGatewayInterface.");
        }

        return app($class);
    }
}