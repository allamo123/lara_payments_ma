<?php

namespace Ma\Payment\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Ma\Payment\Exceptions\CustomerNotFoundException;
use Ma\Payment\Models\PaymentCustomer;

class PaymentCustomerRepository
{
    protected PaymentCustomer $customer;

    public function __construct() {
        $this->customer = new PaymentCustomer();
    }
    public function findCustomer(int $user_id): PaymentCustomer|null
    {
        return $this->customer->query()
                    ->where('user_id', $user_id)
                    ->first();
    }

    public function createCustomer(array $data): PaymentCustomer
    {
        return $this->customer->create($data);
    }
    
    public function updateCustomer(int $user_id, array $data, $gateway): PaymentCustomer
    {
        $customer = $this->findCustomer($user_id);

        if (!$customer) {
            throw new CustomerNotFoundException($user_id);
        }
        $customer->update($data);
        return $customer;
    }

    public function getCustomertTransactions(int $user_id, ?string $status = null): Collection
    {
        $customer = $this->findCustomer($user_id);

        if (!$customer) {
            throw new CustomerNotFoundException($user_id);
        }

        $transactions = $customer->paymentTransactions();

        if (isset($status)) {
           $transactions->where('status', $status);
        }

        return $transactions->get();
    }
}