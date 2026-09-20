# 2. Installation

[← Documentation index](README.md)

---

## 1. Install the package

```bash
composer require ma-lara/payments
```

Laravel package discovery automatically registers:

* the service provider `Ma\Payment\MaPaymentServiceProvider`
* the facade alias `MaPayment` → `Ma\Payment\Facades\MaPayment`

If your application disables package discovery, register the provider manually in
`config/app.php`.

---

## 2. Publish the configuration (recommended)

```bash
php artisan vendor:publish --tag=ma-payment-config
```

This publishes [`ma_payment_conf.php`](../config/ma_payment_conf.php) to your
application's `config/` directory.

Then add the credentials you need to your `.env` file. Every supported variable is
listed in [7. Configuration](07-configuration.md).

> The driver registry is merged from the package as the `ma-drivers` config key and is
> normally **not** published. See
> [6. Gateway Architecture](06-gateway-architecture.md) if you want to add a gateway.

---

## 3. Publish and run the migrations

The package ships its migrations. Publish and run them:

```bash
php artisan vendor:publish --tag=ma-payment-migrations

php artisan migrate
```

### Tables created by the package

| Table                           | Purpose                                                    |
| ------------------------------- | ---------------------------------------------------------- |
| `payment_customers`             | Maps application users to gateway customers                |
| `payment_transactions`          | Stores payment attempts, their gateway references, and the subscription link (when the payment belongs to a subscription) |
| `refunded_payment_transactions` | Stores refund records                                      |
| `customer_cards`                | Stores saved card records (Paymob `TOKEN` callbacks)       |
| `subscription_plans`            | Local mirror of Paymob subscription plans                  |
| `subscriptions`                 | Local mirror of customer subscriptions                     |
| `subscription_webhook_events`   | Idempotency log for subscription lifecycle webhooks         |

The subscription tables were added in **v2.1.0**. Existing installations from earlier
`v2.x` releases only need to run the new migrations; no existing table or column is
modified destructively.

---

## 4. Publish the views / frontend assets (optional)

Only needed if you use the optional Stripe Blade card component:

```bash
php artisan vendor:publish --tag=ma-payment-views
```

This publishes:

* the Blade view to `resources/views/vendor/ma_payment`
* the JavaScript asset to `public/js/vendor/ma_payment`

See [4. Payments](04-payments.md) for the component.

---

## 5. Run a queue worker (required for queue-based features)

Two package jobs are queued:

* `Ma\Payment\Jobs\UpdateRefundTransactionJob` — dispatched by the Stripe webhook flow.
* `Ma\Payment\Jobs\UpdateSubscriptionJob` — dispatched when a subscription lifecycle
  webhook is processed.

Because both implement `Illuminate\Contracts\Queue\ShouldQueue`, your application must
run a queue worker:

```bash
php artisan queue:work
```

If no worker is running, refund state updates and **subscription state updates are
never applied locally**, even though the webhook itself was received.

---

## 6. Expose your callback routes

The package **does not register any route or controller**. Your Laravel application
owns the HTTP endpoints that receive gateway callbacks, and forwards the payload to the
resolved gateway:

```php
use Illuminate\Http\Request;
use Ma\Payment\Facades\MaPayment;

Route::post('/paymob/callback', function (Request $request) {
    return response()->json(
        MaPayment::driver('paymob')->verify($request->all())
    );
});
```

A subscription **lifecycle** webhook uses a different entry point:

```php
Route::post('/paymob/subscription/webhook', function (Request $request) {
    MaPayment::driver('paymob')->subscription()->lifeCycle($request->all());

    return response()->json(['received' => true]);
});
```

* Paymob callbacks are covered in
  [4. Payments → Payment Callbacks / Webhooks](04-payments.md) and
  [5. Subscriptions → Subscription Callbacks](05-subscriptions.md).
* Stripe webhooks are covered in [4. Payments → Payment Callbacks / Webhooks](04-payments.md).
* If your callback routes are inside a CSRF-protected group, exclude them from CSRF
  verification for those endpoints only.

---

## 7. Register the subscription webhook URL with Paymob

When a subscription is created, the package calls Paymob's
`register_webhook` endpoint for that subscription using the URL from:

```env
PAYMOB_SUBSCRIPTION_WEBHOOK_URL=https://your-app.test/paymob/subscription/webhook
```

Set this variable before going live, otherwise Paymob has no URL to send subscription
lifecycle events to. See [7. Configuration](07-configuration.md).

---

## Installation checklist

| Step                                                            | Required                                |
| --------------------------------------------------------------- | --------------------------------------- |
| `composer require ma-lara/payments`                             | ✅                                       |
| Publish `ma-payment-config` and fill `.env`                     | ✅                                       |
| Publish `ma-payment-migrations` + `php artisan migrate`         | ✅                                       |
| Define your callback route(s)                                   | ✅ (payments and/or subscriptions)       |
| `PAYMOB_SUBSCRIPTION_WEBHOOK_URL`                               | ✅ when using subscriptions              |
| Run a queue worker                                              | ✅ for refund job + subscription updates |
| Publish `ma-payment-views`                                      | Optional (Stripe Blade component only)   |

---

[← Previous: 1. Introduction](01-introduction.md) · [Next: 3. Quick Start →](03-quick-start.md)

