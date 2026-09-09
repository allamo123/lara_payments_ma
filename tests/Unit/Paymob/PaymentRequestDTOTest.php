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

        $PaymentDTO = PaymentRequestDTO::fromArray($data);

        $this->assertInstanceOf(PaymentRequestDTO::class, $PaymentDTO);

        $this->assertSame(new Money($data['amount'])->toCents(), $PaymentDTO->amount->toCents());
        
        $this->assertSame($data['currency'], $PaymentDTO->currency);

        $this->assertSame(new UserEmail($data['customer']['email'])->value(), $PaymentDTO->user_email->value());
        $this->assertSame($data['customer']['id'], $PaymentDTO->user_id);
        $this->assertSame($data['customer']['first_name'], $PaymentDTO->user_first_name);
        $this->assertSame($data['customer']['last_name'], $PaymentDTO->user_last_name);
        $this->assertSame($data['customer']['phone'], $PaymentDTO->user_phone);

        $this->assertSame($data['source'], $PaymentDTO->source);
        $this->assertSame($data['gateway'], $PaymentDTO->gateway);
        

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