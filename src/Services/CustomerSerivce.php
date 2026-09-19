<?php

namespace Ma\Payment\Services;

use Ma\Payment\Models\Subscription;
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

        return $this->customer->createCustomer($customerData);
    }

    public function updateCustomer(int $userId, array $customerData, string $gateway)
    {
         return $this->customer->updateCustomer($userId, $customerData, $gateway);
    }

    public function getCustomerByEmail(string $email)
    {
        return $this->customer->getCustomerByEmail($email);
    }

    public function SaveNewCard(array $cardData): void
    {
        $customer = $this->getCustomerByEmail($cardData['email']);
        $customer->cards()->create($cardData);
    }

    public function subscribe(string $email, array $data): Subscription
    {
        $customer = $this->getCustomerByEmail($email);
        return $customer->subscriptions()->create($data);
    }
}