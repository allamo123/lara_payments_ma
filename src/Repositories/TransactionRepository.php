<?php

namespace Ma\Payment\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Ma\Payment\Interfaces\TransactionRepositoryInterface;
use Ma\Payment\Models\PaymentTransaction;

class TransactionRepository implements TransactionRepositoryInterface
{
    private PaymentTransaction $transaction;

    public function __construct()
    {
        $this->transaction = new PaymentTransaction();
    }

    public function findByLocaleId(int $id): ?PaymentTransaction
    {
        return $this->transaction->find($id);
    }

    public function getAll(?string $status = null): Collection
    {
        $transactions = $this->transaction->query();

        if (isset($status)) {
            $transactions->where('status', $status);
        }

        return $transactions->get();
    }

    public function getTransactionByOrderId(int $orderId): ?PaymentTransaction
    {
        return $this->transaction->query()
            ->where('order_id', $orderId)
            ->first();
    }

    public function getTransactionByRef(string $ref): ?PaymentTransaction
    {
        return $this->transaction->query()
            ->where('gateway_reference', $ref)
            ->first();
    }

    public function getTransactionByGateway(string $gateway): ?PaymentTransaction
    {
        return $this->transaction->query()
            ->where('gateway', $gateway)
            ->first();
    }

    public function LockUpdateTransactionByRefrence(string $ref, array $data): ?PaymentTransaction
    {
        $transaction = $this->transaction->query()
            ->where('gateway_reference', $ref)
            ->lock()
            ->first();

        $transaction->update($data);

        return $transaction;
    }

    public function createOrUpdate(?int $id, array $data): void
    {
        $this->transaction->updateOrCreate(
            ['id' => $id],
            $data
        );
    }

    public function updateByOrderId(string $orderId, array $data): void
    {
        $transaction = $this->getTransactionByOrderId($orderId);

        $transaction->update($data);
    }
}
