<?php

namespace Ma\Payment\Services;

use Ma\Payment\Repositories\PaymentCustomerRepository;

final class CustomerSerivce
{
    public function __construct(private PaymentCustomerRepository $customer)
    {}

    public function getCustomerByUserId(int $userId)
    {
        return $this->customer->findCustomer($userId);
    }

    public function getCustomerOrCreate(array $customerData)
    {
        if (isset($customerData['user_id'])) {

            $customer = $this->customer->findCustomer($customerData['user_id']);

            if(!$customer)
            {
                return $this->customer->createCustomer($customerData);
            }

            return $customer;
        }
    }

    public function updateCustomer(int $userId, array $customerData, string $gateway)
    {
         return $this->customer->updateCustomer($userId, $customerData, $gateway);
    }
}