<?php

namespace Ma\Payment\Gateways\Stripe\Services;

use Stripe\StripeClient;

final class StripeApiService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('ma-payment.STRIPE_API_SECRET'));
    }

    public function createCustomer(array $data)
    {
        return $this->stripe->customers->create($data);
    }

    public function createPaymentIntent(array $data) :array
    {  
        return $this->stripe->paymentIntents->create([
            'amount' => $data['amount'],

            'currency' => strtolower($data['currency']),

            'customer' => $data['gateway_customer_id'],

            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],

            'confirm' => true, 

            'payment_method' => $data['payment_method']['id'],

            'metadata' => [],
        ])->toArray();
    }

    public function refundPayment(string $transactionId, int $amount): array
    {
        return $this->stripe->refunds->create([
            'payment_intent' => $transactionId,
            'amount' => $amount,
        ])->toArray();
    }

    public function retryPayment(string $transactionId, string $paymentMethodId): array
    {
         return $this->stripe->paymentIntents->confirm(
            $transactionId,
            [
                'payment_method' => $paymentMethodId,
            ]
        )->toArray();
    }
}

