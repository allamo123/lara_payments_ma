<?php

namespace Ma\Payments;

use Illuminate\Support\ServiceProvider;
use Ma\Payments\Classes\FawryPayment;
use Ma\Payments\Classes\HyperPayPayment;
use Ma\Payments\Classes\KashierPayment;
use Ma\Payments\Classes\PaymobPayment;
use Ma\Payments\Classes\PayPalPayment;
use Ma\Payments\Classes\PaytabsPayment;
use Ma\Payments\Classes\ThawaniPayment;
use Ma\Payments\Classes\TapPayment;
use Ma\Payments\Classes\OpayPayment;
use Ma\Payments\Classes\PaymobWalletPayment;

class MaPaymentsServiceProvider extends ServiceProvider
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
        
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ma');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'ma');
        

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/ma'),
        ]);
        $this->publishes([
            __DIR__ . '/../config/ma-payments.php' => config_path('ma-payments.php'),
        ]);
        $this->publishes([
            __DIR__ . '/../resources/lang' => lang_path('vendor/payments'),
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->bind(PaymobPayment::class, function () {
            return new PaymobPayment();
        });
        $this->app->bind(FawryPayment::class, function () {
            return new FawryPayment();
        });
        $this->app->bind(ThawaniPayment::class, function () {
            return new ThawaniPayment();
        });
        $this->app->bind(PaypalPayment::class, function () {
            return new PaypalPayment();
        });
        $this->app->bind(HyperPayPayment::class, function () {
            return new HyperPayPayment();
        });
        $this->app->bind(KashierPayment::class, function () {
            return new KashierPayment();
        });
        $this->app->bind(TapPayment::class, function () {
            return new TapPayment();
        });
        $this->app->bind(OpayPayment::class, function () {
            return new OpayPayment();
        });
        $this->app->bind(PaymobWalletPayment::class, function () {
            return new PaymobWalletPayment();
        });
        $this->app->bind(PaytabsPayment::class, function () {
            return new PaytabsPayment();
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
            __DIR__ . '/../config/ma-payments.php', 'ma-payments'
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
            __DIR__ . '/../config/ma-payments.php' => config_path('ma-payments.php'),
        ], 'ma-payments-config');
        $this->publishes([
            __DIR__ . '/../resources/lang' => lang_path('vendor/payments'),
        ], 'ma-payments-lang');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/payments'),
        ], 'ma-payments-views');

    }
}