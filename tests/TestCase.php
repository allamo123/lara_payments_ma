<?php

namespace Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Ma\Payment\MaPaymentServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ma-payment', [
            'PAYMOB_API_KEY' => 'test-api-key',
            'PAYMOB_API_SECRET' => 'test-api-secret',
            'PAYMOB_INTEGRATION_ID' => 'test-integration-id',
            'PAYMOB_IFRAME_ID' => 'test-iframe-id',
            'PAYMOB_HMAC' => 'test-hmac',
            'PAYMOB_CURRENCY' => 'EGP',
            'PAYMOB_WALLET_INTEGRATION_ID' => 'test-wallet-integration-id',
            'STRIPE_API_PUBLISHED_KEY' => 'test-stripe-published-key',
            'STRIPE_API_SECRET' => 'test-stripe-secret',
            'STRIPE_WEBHOOK_SECRET' => 'test-stripe-webhook-secret',
            'STRIPE_BASE_URL' => 'https://api.stripe.com',
            'STRIPE_CURRENCY' => 'USD',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate')->run();
    }
}


