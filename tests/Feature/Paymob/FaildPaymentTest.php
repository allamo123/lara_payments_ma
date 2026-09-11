<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Ma\Payment\Enums\PaymentStatus;
use Ma\Payment\Facades\MaPayment;
use Ma\Payment\ValueObjects\Money;
use Tests\TestCase;

class FaildPaymentTest extends TestCase
{
    public function test_failed_payment_status(): void
    {
        $data = [
            'amount' => 100.00,
            'currency' => 'USD',
            'customer' => [
                'id' => 1,
                'first_name' => 'Mohamed',
                'last_name' => 'Allam',
                'email' => 'mo@example.com',
                'phone' => '+201000000000',
            ],
            'source' => 'card',
        ];

        $orderId = 67890;

        // Fake the Paymob API responses with an order whose payment_status is `failed`
        Http::fake([
            'https://accept.paymobsolutions.com/*' => Http::sequence()
                ->push(['token' => 'AUTH_TOKEN'], 200)                              // POST /api/auth/tokens
                ->push([                                                            // POST /api/api/ecommerce/orders
                    'id' => $orderId,
                    'amount_cents' => new Money($data['amount'])->toCents(),
                    'payment_status' => PaymentStatus::FAILED,
                ], 200)
                ->push([                                                            // POST /api/acceptance/payment_keys
                    'token' => 'PAYMENT_TOKEN',
                    "expiration" => 36000,
                    "amount_cents" => new Money($data['amount'])->toCents(),
                    "order_id" => $orderId,
                    'billing_data' => [
                        'first_name' => $data['customer']['first_name'],
                        'last_name' => $data['customer']['last_name'],
                    ],
                ], 200),
        ]);

        $paymob = MaPayment::driver('paymob');

        // The failed payment still returns the iframe URL, but the transaction is stored as `failed`
        $payment = $paymob->pay($data);

        // Assert the iframe URL is still returned
        $this->assertIsString($payment);
        $this->assertStringContainsString('/acceptance/iframes/test-iframe-id', $payment);
        $this->assertStringContainsString('payment_token=PAYMENT_TOKEN', $payment);

        // Assert the transaction is persisted with status `failed`
        $this->assertDatabaseHas('payment_transactions', [
            'gateway' => 'paymob',
            'order_id' => $orderId,
            'status' => PaymentStatus::FAILED->value,
            'currency' => $data['currency'],
        ]);

        // Assert the transaction amount is stored in minor units (cents)
        $transaction = $paymob->getTransactions(PaymentStatus::FAILED->value);

        $this->assertCount(1, $transaction);
        $this->assertEquals(new Money($data['amount'])->toCents(), $transaction[0]->minor_amount);
        $this->assertEquals(PaymentStatus::FAILED->value, $transaction[0]->status);
        $this->assertEquals($orderId, $transaction[0]->order_id);
    }
}