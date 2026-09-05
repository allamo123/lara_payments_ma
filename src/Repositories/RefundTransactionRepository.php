<?php

namespace Ma\Payment\Repositories;

use Illuminate\Support\Facades\Log;
use Ma\Payment\Models\RefundedPaymentTransaction;

class RefundTransactionRepository
{
    private RefundedPaymentTransaction $refundTransaction;

    public function __construct()
    {
        $this->refundTransaction = new RefundedPaymentTransaction();
    }

    public function getRefundTransaction(string $transactionId): RefundedPaymentTransaction
    {
        return $this->refundTransaction->query()
            ->where('transaction_id', $transactionId)
            ->lockForUpdate()
            ->first();
    }

    public function createRefundTransaction(array $data): void
    {
        $this->refundTransaction->create($data);
    }
    
    public function updateRefundTransaction(string $transactionId, array $data): RefundedPaymentTransaction
    {
       $transaction = $this->getRefundTransaction($transactionId);
       $transaction->update($data);
       return $transaction;
    }
}