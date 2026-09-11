<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Ma\Payment\Enums\PaymentStatus;
use Ma\Payment\Facades\MaPayment;
use Ma\Payment\ValueObjects\Money;
use Tests\TestCase;

class SuccessfulPaymentTest extends TestCase
{
    public function test_successful_payment_flow(): void
    {
        $data = [
            'amount' => 150.50,
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

        $orderId = 12345;
        $amount = new Money($data['amount']);

        Http::fake([
            'https://accept.paymobsolutions.com/*' => Http::sequence()
                ->push(['token' => 'AUTH_TOKEN'], 200)                              // POST /api/auth/tokens
                ->push([                                                            // POST /api/api/ecommerce/orders
                    'id' => $orderId,
                    'amount_cents' => $amount->toCents(),
                    'payment_status' => PaymentStatus::PENDING->value,
                ], 200)
                ->push([                                                            // POST /api/acceptance/payment_keys
                    'token' => 'PAYMENT_TOKEN',
                    "expiration" => 36000,
                    "amount_cents" => $amount->toCents(),
                    "order_id" => $orderId,
                    'billing_data' => [
                        'first_name' => $data['customer']['first_name'],
                        'last_name' => $data['customer']['last_name'],
                    ],
                ], 200),
        ]);

        $paymob = MaPayment::driver('paymob');

        $payment = $paymob->pay($data);

        Http::assertSent(function ($request) use ($data) {
            return $request->url() === 'https://accept.paymobsolutions.com/api/acceptance/payment_keys'
                && $request['billing_data']['first_name'] === $data['customer']['first_name']
                && $request['billing_data']['last_name'] === $data['customer']['last_name'];
        });

        $this->assertIsString($payment);
        $this->assertStringContainsString('/acceptance/iframes/test-iframe-id', $payment);
        $this->assertStringContainsString('payment_token=PAYMENT_TOKEN', $payment);

        Http::assertSentInOrder([
            'https://accept.paymobsolutions.com/api/auth/tokens',
            'https://accept.paymobsolutions.com/api/api/ecommerce/orders',
            'https://accept.paymobsolutions.com/api/acceptance/payment_keys',
        ]);
    }
}