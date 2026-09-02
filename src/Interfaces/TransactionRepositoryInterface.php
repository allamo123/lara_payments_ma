<?php

namespace Ma\Payment\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Ma\Payment\Models\PaymentTransaction;

interface TransactionRepositoryInterface
{
    public function findByLocaleId(int $id): ?PaymentTransaction;

    public function getAll(?string $status = null): Collection;

    public function getTransactionByOrderId(int $orderId): ?PaymentTransaction;

    public function getTransactionByRef(string $ref): ?PaymentTransaction;

    public function getTransactionByGateway(string $gateway): ?PaymentTransaction;

    public function LockUpdateTransactionByRefrence(string $ref, array $data): ?PaymentTransaction;

    public function createOrUpdate(?int $id, array $data):void;

    public function updateByOrderId(string $orderId, array $data): void;

}