<?php

namespace Ma\Payment\DTOS;

use Ma\Payment\ValueObjects\Money;

final class PaymobSubscriptionPlanResponseDTO
{
    public int $frequency;
    public string $name;
    public int $reminder_days;
    public int $retrial_days ; 
    public Money $amount_cents;
    public string $planType;
    public bool $use_transaction_amount;
    public bool $is_active;
    public int $number_of_deductions;
    public string $planId;

    public function __construct(
        int $frequency, 
        string $name, 
        int $reminder_days, 
        int $retrial_days , 
        int $amount_cents,
        bool $use_transaction_amount = false,
        bool $is_active = true,
        string $planType = 'rent',
        int $number_of_deductions,
        string $planId,
    )
    {
        $this->frequency = $frequency;
        $this->name = $name;
        $this->reminder_days = $reminder_days;
        $this->retrial_days = $retrial_days;
        $this->amount_cents = new Money($amount_cents);
        $this->use_transaction_amount = $use_transaction_amount;
        $this->is_active = $is_active;
        $this->planType = $planType;
        $this->number_of_deductions = $number_of_deductions;
        $this->planId = $planId;

    }

    public static function fromArray(array $data): PaymobSubscriptionPlanResponseDTO
    {
        return new self (
            frequency: $data['frequency'],
            name: $data['name'],
            reminder_days: $data['reminder_days'],
            retrial_days: $data['retrial_days'],
            amount_cents: $data['amount_cents'],
            planType: $data['planType'] ?? 'rent',
            use_transaction_amount: $data['use_transaction_amount'] ?? false,
            is_active: $data['is_active'] ?? true,
            number_of_deductions: $data['number_of_deductions'],
            planId: $data['id'],
        );
    }

    public function toDatabase(array $response): array
    {
        return [
            'gateway' => 'paymob',
            'gateway_plan_id' => $this->planId,
            'name' => $this->name,
            'minor_amount' => $this->amount_cents->value(),
            'billing_interval_count' => $this->frequency,
            'billing_cycles' => $this->number_of_deductions,
            'is_active' => $this->is_active,
            'metadata' => json_encode($response),
        ];
    }
}