<?php

namespace Ma\Payment;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

use Ma\Payment\Gateways\Paymob\Services\PaymobApiService;
use Ma\Payment\Gateways\Paymob\PaymobGateway;
use Ma\Payment\Interfaces\PaymentGatewayInterface;
use Ma\Payment\Gateways\Stripe\StripeGateway;
use Ma\Payment\Services\ClientApiService;
use Ma\Payment\Interfaces\TransactionRepositoryInterface;
use Ma\Payment\Repositories\TransactionRepository;

class MaPaymentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configure();
        $this->registerPublishing();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ma-payment');
        Blade::anonymousComponentPath(resource_path('views/vendor/ma_payment'),'ma-payment');
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views','ma-payment');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            PaymentGatewayInterface::class,
            PaymobGateway::class,
        );
        
        $this->app->bind(
            PaymentGatewayInterface::class,
            StripeGateway::class,
        );

        $this->app->bind(
            TransactionRepositoryInterface::class,
            TransactionRepository::class
        );

        $this->app->bind(PaymobApiService::class, function ($app) {
            return new PaymobApiService(
                $app->make(ClientApiService::class)
            );
        });
        
    }

    /**
     * Setup the configuration for ma Payments.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ma_payment_conf.php', 'ma-payment'
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/ma_payment_drivers.php', 'ma-drivers'
        );
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        $this->publishes([
            __DIR__ . '/../config/ma_payment_conf.php' => config_path('ma_payment_conf.php'),
        ], 'ma-payment-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/ma_payment'),
            __DIR__ . '/../resources/js' => \public_path('js/vendor/ma_payment'),
        ], 'ma-payment-views');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ma-payment-migrations');
    }
}