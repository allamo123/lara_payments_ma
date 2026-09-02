<?php

namespace Ma\Payment\Exceptions;

use Ma\Payment\ValueObjects\Money;
use RuntimeException;

class RefundAmountGreaterThanTransactionAmountException extends RuntimeException
{
    public function __construct(
        public readonly int $txnId,
        public readonly int $transactionAmount,
        public readonly string $refundAmount,
    ) {
        $refundRequest = new Money($refundAmount)->toPounds();
        $actualTxnAmount = new Money($transactionAmount)->toPounds();

        parent::__construct(
            "Transaction with ID $txnId cannot proccess refund the refund amount ($refundRequest) greater than actual available remain transaction amount ($actualTxnAmount)."
        );
    }
}