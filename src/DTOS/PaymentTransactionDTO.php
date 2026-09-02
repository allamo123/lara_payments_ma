<?php

namespace Ma\Payment\DTOs;

use Ma\Payment\ValueObjects\Money;

final readonly class PaymentTransactionDTO
{
    public function __construct(
        public readonly Money $amount,
        public readonly int $customerId,
        public readonly string $source,
        public readonly string $gatewayName,
        public readonly string $status,
        public readonly string $currency,
        public readonly array $metadata,
        public readonly ?string $gatewayRefrence,
        public readonly ?string $orderId = null,
        public readonly ?string $source_subtype = null,
    ) {}

    public function toDatabase(): array
    {
        return [
            'minor_amount' => $this->amount->toCents(),
            'customer_id' => $this->customerId,
            'source' => $this->source,
            'source_subtype' => $this->source_subtype ?? null,
            'gateway' => $this->gatewayName,
            'order_id' => $this->orderId ? $this->orderId : null,
            'gateway_reference' => $this->gatewayRefrence ? $this->gatewayRefrence : null,
            'status' => $this->status,
            'currency' => $this->currency,
            'meta_data' => json_encode($this->metadata, JSON_PRETTY_PRINT)
        ];
    }
}