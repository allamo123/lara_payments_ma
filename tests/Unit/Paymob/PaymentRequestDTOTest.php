<?php

namespace Tests\Unit\Paymob;

use PHPUnit\Framework\TestCase;

use Ma\Payment\DTOS\PaymentRequestDTO;
use Ma\Payment\ValueObjects\Money;
use Ma\Payment\ValueObjects\UserEmail;
use Ma\Payment\ValueObjects\UserId;

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

        $money = new Money($data['amount']);
        $email = new UserEmail($data['customer']['email']);
        $userId = new UserId($data['customer']['id']);

        $this->assertSame($money->toCents(), $PaymentDTO->amount->toCents());
        
        $this->assertSame($data['currency'], $PaymentDTO->currency);

        $this->assertSame($email->value(), $PaymentDTO->user_email->value());
        $this->assertSame($userId->value(), $PaymentDTO->user_id->value());
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
                'id' => 0,
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