<?php

namespace Tests\Unit\Paymob;

use InvalidArgumentException;
use Ma\Payment\DTOs\PaymentTransactionDTO;
use Ma\Payment\ValueObjects\Money;
use Ma\Payment\ValueObjects\UserId;
use PHPUnit\Framework\TestCase;

class PaymentTransactionDTOTest extends TestCase
{
    public function test_valid_payment_transaction_DTO(): void
    {
        $response = [
            'amount' => 400,
            'locale_customer_id' => 1,
            'source' => 'card',
            'source_subtype' => 'visa',
            'gateway' => 'paymob',
            'orderId' => 23456,
            'payment_status' => 'succeeded',
            'currency' => 'EGP'
        ];

        $transactionDTO = PaymentTransactionDTO::fromArray($response);

        $this->assertInstanceOf(PaymentTransactionDTO::class, $transactionDTO);

        $amount = new Money($response['amount']);
        $userId = new UserId($response['locale_customer_id']);

        $this->assertSame($amount->toCents(), $transactionDTO->amount->toCents());

        $this->assertSame($userId->value(), $transactionDTO->customerId->value());
        $this->assertSame($response['source'], $transactionDTO->source);
        $this->assertSame($response['source_subtype'], $transactionDTO->source_subtype);
        $this->assertSame($response['gateway'], $transactionDTO->gatewayName);
        $this->assertSame((int) $response['orderId'], (int) $transactionDTO->orderId);
        $this->assertSame($response['payment_status'], $transactionDTO->status);
        $this->assertSame($response['currency'], $transactionDTO->currency);
        $this->assertSame($response, $transactionDTO->metadata);
    }

    public function test_invalid_payment_transaction_DTO_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $response = [
            'amount' => -400,
            'locale_customer_id' => 1,
            'source' => 'card',
            'source_subtype' => 'visa',
            'gateway' => 'paymob',
            'orderId' => 23456,
            'payment_status' => 'succeeded',
            'currency' => 'EGP'
        ];

        PaymentTransactionDTO::fromArray($response);
    }

    public function test_invalid_payment_transaction_DTO_locale_customer_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $response = [
            'amount' => 400,
            'locale_customer_id' => 0,
            'source' => 'card',
            'source_subtype' => 'visa',
            'gateway' => 'paymob',
            'orderId' => 23456,
            'payment_status' => 'succeeded',
            'currency' => 'EGP'
        ];

        PaymentTransactionDTO::fromArray($response);
    }

    public function test_invalid_payment_transaction_DTO_gateway_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $response = [
            'amount' => 400,
            'locale_customer_id' => 1,
            'source' => 'card',
            'source_subtype' => 'visa',
            'gateway' => '',
            'orderId' => 23456,
            'payment_status' => 'succeeded',
            'currency' => 'EGP'
        ];

        PaymentTransactionDTO::fromArray($response);
    }

    public function test_it_converts_payment_transaction_dto_to_database_format(): void
    {
        $response = [
            'amount' => 400,
            'locale_customer_id' => 1,
            'source' => 'card',
            'source_subtype' => 'visa',
            'gateway' => 'paymob',
            'orderId' => 23456,
            'payment_status' => 'succeeded',
            'currency' => 'EGP'
        ];

        $transactionDTO = PaymentTransactionDTO::fromArray($response);

        $db_data = $transactionDTO->toDatabase();

        $this->assertIsArray($db_data);

        $this->assertArrayHasKey('gateway', $db_data);
        $this->assertArrayHasKey('order_id', $db_data);
        $this->assertArrayHasKey('customer_id', $db_data);
        $this->assertArrayHasKey('minor_amount', $db_data);
        $this->assertArrayHasKey('currency', $db_data);
        $this->assertArrayHasKey('source', $db_data);
        $this->assertArrayHasKey('source_subtype', $db_data);
        $this->assertArrayHasKey('status', $db_data);
        $this->assertArrayHasKey('meta_data', $db_data);

        $amount = new Money($response['amount']);

        $this->assertSame($response['gateway'], $db_data['gateway']);
        $this->assertSame((int) $response['orderId'], $db_data['order_id']);
        $this->assertSame((int) $response['locale_customer_id'], $db_data['customer_id']);
        $this->assertSame($amount->toCents(), $db_data['minor_amount']);
        $this->assertSame($response['currency'], $db_data['currency']);
        $this->assertSame($response['source'], $db_data['source']);
        $this->assertSame($response['source_subtype'], $db_data['source_subtype']);
        $this->assertSame($response['payment_status'], $db_data['status']);
        $this->assertSame(json_encode($response, JSON_PRETTY_PRINT), $db_data['meta_data']);


    }

}