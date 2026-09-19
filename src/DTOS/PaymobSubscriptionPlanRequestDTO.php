<?php

namespace Ma\Payment\DTOS;

use Ma\Payment\ValueObjects\Money;

final class PaymobSubscriptionPlanRequestDTO
{
    public int $frequency;
    public string $name;
    public int $reminder_days;
    public int $retrial_days ; 
    public Money $amount;
    public string $planType;
    public bool $use_transaction_amount;
    public bool $is_active;
    public ?int $number_of_deductions;

    public function __construct(
        int $frequency, 
        string $name, 
        int $reminder_days, 
        int $retrial_days , 
        Money $amount,
        bool $use_transaction_amount = false,
        bool $is_active = true,
        string $planType = 'rent',
        ?int $number_of_deductions = null,
    )
    {
        $this->frequency = $frequency;
        $this->name = $name;
        $this->reminder_days = $reminder_days;
        $this->retrial_days = $retrial_days;
        $this->amount = $amount;
        $this->use_transaction_amount = $use_transaction_amount;
        $this->is_active = $is_active;
        $this->planType = $planType;
        $this->number_of_deductions = $number_of_deductions;
    }

    public static function fromArray(array $data): self
    {
        return new self (
            frequency: $data['frequency'],
            name: $data['name'],
            reminder_days: $data['reminder_days'],
            retrial_days: $data['retrial_days'],
            amount: new Money($data['amount']),
            planType: $data['planType'] ?? 'rent',
            use_transaction_amount: $data['use_transaction_amount'] ?? false,
            is_active: $data['is_active'] ?? true,
            number_of_deductions: $data['number_of_deductions'],
        );
    }

    public function apiRequestData(): array
    {
        return [
            "frequency" => $this->frequency,
            "name"  => $this->name,
            "reminder_days" => $this->reminder_days,
            "retrial_days" => $this->retrial_days,
            "plan_type" => $this->planType,
            "amount_cents" => $this->amount->toCents(),
            "use_transaction_amount" => $this->use_transaction_amount,
            "is_active" => $this->is_active,
            'number_of_deductions' => $this->number_of_deductions,
        ];
    }
}