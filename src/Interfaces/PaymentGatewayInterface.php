<?php

namespace Ma\Payment\Interfaces;

use Illuminate\Database\Eloquent\Collection;
// use Ma\Payment\DTOS\PaymentRequestDTO;

interface PaymentGatewayInterface
{
    public function pay(array $data, bool $isRetry): string|array;

    public function verify(array|string $data, ?string $param = null): array;

    public function getTransactions(?string $status): Collection;

    public function getCustomerTransactions(int $id, ?string $status): Collection;

    public function getGatewayTransactionByOrderId(int $orderId);

    public function retryPayment(int $id, ?string $param): string|array;

    public function refund(string $localTransactionId, int $amount): void;
}