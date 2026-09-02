<?php

namespace Ma\Payment\DTOS;

use Ma\Payment\ValueObjects\Money;
use Ma\Payment\ValueObjects\UserEmail;

final class PaymentRequestDTO
{
    public readonly string $gateway;
    public readonly ?array $payment_method;
    public readonly Money $amount;
    public readonly string $currency;
    public readonly int $user_id;
    public ?string $gateway_customer_id;
    public readonly string $user_first_name; 
    public readonly string $user_last_name;
    public readonly UserEmail $user_email;
    public readonly string $user_phone; 
    public readonly string $source;

    public function __construct(
        string $gateway,
        Money $amount,
        string $currency,
        int $user_id, 
        string $user_first_name, 
        string $user_last_name, 
        UserEmail $user_email, 
        string $user_phone, 
        string $source,
        ?string $gateway_customer_id = null,
        ?array $payment_method = null,
    ){
        $this->gateway = $gateway;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->user_id = $user_id;
        $this->user_first_name = $user_first_name;
        $this->user_last_name = $user_last_name;
        $this->user_email = $user_email;
        $this->user_phone = $user_phone;
        $this->source = $source;
        $this->gateway_customer_id = isset($gateway_customer_id) ? $gateway_customer_id : null;
        $this->payment_method = isset($payment_method) ? $payment_method : null;
    }

     public static function fromArray(array $data): self
    {
        return new self(
            gateway: $data['gateway'],
            amount: new Money($data['amount']),
            currency: $data['currency'],
            user_id: $data['customer']['id'],
            user_first_name: $data['customer']['first_name'],
            user_last_name: $data['customer']['last_name'],
            user_email: new UserEmail($data['customer']['email']),
            user_phone: $data['customer']['phone'],
            source: $data['source'],
            gateway_customer_id: isset($data['customer']['gateway_customer_id']) ? $data['customer']['gateway_customer_id'] : null,
            payment_method: isset($data['payment_method']) ? $data['payment_method'] : null,
        );
    }

    public function customer(): array
    {
        return [
            'gateway' => $this->gateway,
            'user_id' => $this->user_id,
            'gateway_customer_id' => $this->gateway_customer_id,
            'name' => $this->user_first_name.' '.$this->user_last_name,
            'email'  => $this->user_email->value(),
            'phone'  => $this->user_phone,
        ];
    }

    public function customerApi(): array
    {
        return [
            'id' => $this->gateway_customer_id,
            'name' => $this->user_first_name.' '.$this->user_last_name,
            'email'  => $this->user_email->value(),
            'phone'  => $this->user_phone,

        ];
    }

    public function paymentData(): array
    {
        return [
            'payment_method' => $this->payment_method,
            'amount' => $this->amount->toCents(),
            'gateway_customer_id' => $this->gateway_customer_id,
            'user_id' => $this->user_id,
            'currency' => $this->currency,
            'source' => $this->source,
        ];
    }

    public function attachGatewayCustomerId(?string $gateway_customer_id): void
    {
        $this->gateway_customer_id = $gateway_customer_id;
    }
}