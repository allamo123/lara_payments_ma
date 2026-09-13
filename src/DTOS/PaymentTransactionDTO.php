<?php

namespace Ma\Payment\DTOS;

use InvalidArgumentException;
use Ma\Payment\ValueObjects\Money;
use Ma\Payment\ValueObjects\UserId;

final class PaymentTransactionDTO
{
    public function __construct(
        public readonly Money $amount,
        public readonly UserId $customerId,
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
            'customer_id' => $this->customerId->value(),
            'source' => $this->source,
            'source_subtype' => $this->source_subtype ?? null,
            'gateway' => $this->gatewayName,
            'order_id' => $this->orderId ? (int) $this->orderId :  null,
            'gateway_reference' => $this->gatewayRefrence ? $this->gatewayRefrence : null,
            'status' => $this->status,
            'currency' => $this->currency,
            'meta_data' => json_encode($this->metadata, JSON_PRETTY_PRINT)
        ];
    }

    public static function fromArray(array $data): PaymentTransactionDTO
    {
        if (!$data['gateway'] || $data['gateway'] === '') {
            throw new InvalidArgumentException("Not valid gatway name");
            
        }

        return new self (
            amount: new Money($data['amount']),
            customerId: new UserId($data['locale_customer_id']),
            source: $data['source'],
            source_subtype: $data['source_subtype'] ?? null,
            gatewayName: $data['gateway'],
            orderId: isset($data['orderId']) ? $data['orderId'] : null,
            status: $data['payment_status'],
            gatewayRefrence: isset($data['gateway_reference']) ? $data['gateway_reference'] : null,
            currency: $data['currency'],
            metadata: $data,
        );
    }
}