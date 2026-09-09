<?php

namespace Tests\Unit\Paymob;

use PHPUnit\Framework\TestCase;

use Ma\Payment\DTOS\PaymentRequestDTO;
use Ma\Payment\ValueObjects\Money;
use Ma\Payment\ValueObjects\UserEmail;

class PaymentRequestDTOTest extends TestCase
{
    public function test_valid_payment_request_DTO(): void
    {
        $data = [
            'amount' => 150.50,
            'currency' => 'USD',
            'customer' => [
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'phone' => '+201000000000',
            ],
            'source' => 'card',
            'gateway' => 'paymob'
        ];

        $DTO = PaymentRequestDTO::fromArray($data);

        $this->assertInstanceOf(PaymentRequestDTO::class, $DTO);

        $this->assertSame(new Money($data['amount'])->toCents(), $DTO->amount->toCents());
        $this->assertSame($data['currency'], $DTO->currency);

        $this->assertSame(new UserEmail($data['customer']['email'])->value(), $DTO->user_email->value());
        $this->assertSame($data['customer']['id'], $DTO->user_id);
        $this->assertSame($data['customer']['first_name'], $DTO->user_first_name);
        $this->assertSame($data['customer']['last_name'], $DTO->user_last_name);
        $this->assertSame($data['customer']['phone'], $DTO->user_phone);

        $this->assertSame($data['source'], $DTO->source);
        $this->assertSame($data['gateway'], $DTO->gateway);
        

    }

    public function test_invalid_payment_request_dto_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $data = [
            'amount' => -100,
            'currency' => 'EGP',
            'customer' => [
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'phone' => '+201000000000',
            ],
            'source' => 'card',
            'gateway' => 'paymob',
        ];

        PaymentRequestDTO::fromArray($data);

    }
}