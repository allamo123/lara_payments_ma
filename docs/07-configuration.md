# 7. Configuration

[← Documentation index](README.md)

All package configuration lives in two files, and every value is driven by an environment
variable. This chapter collects everything in one place so you do not have to hunt
through the other chapters.

---

## Configuration files

| File                                                          | Config key   | Purpose                                        |
| ------------------------------------------------------------- | ------------ | ---------------------------------------------- |
| [`config/ma_payment_conf.php`](../config/ma_payment_conf.php)   | `ma-payment` | Credentials and settings for Stripe and Paymob |
| [`config/ma_payment_drivers.php`](../config/ma_payment_drivers.php) | `ma-drivers` | Gateway driver registry                   |

The package reads configuration with `config('ma-payment.<KEY>')` and resolves drivers
with `config('ma-drivers')`. Both are also set by the package default file through
`env('<KEY>')`, so **the environment variables are what actually configure the package**;
publishing the configuration file is optional and mainly for reference.

```bash
php artisan vendor:publish --tag=ma-payment-config
```

> If you publish the config file, clear the config cache after changing environment
> variables (`php artisan config:clear`) and never commit real secrets.

---

## Paymob

```env
PAYMOB_API_KEY=
PAYMOB_API_SECRET=
PAYMOB_PUBLIC_KEY=
PAYMOB_INTEGRATION_ID=
PAYMOB_MOTO_INTEGRATION_ID=
PAYMOB_WALLET_INTEGRATION_ID=
PAYMOB_IFRAME_ID=
PAYMOB_SUBSCRIPTION_WEBHOOK_URL=
PAYMOB_HMAC=
PAYMOB_CURRENCY=EGP
```

| Variable                          | Used for                                                       | Required for                        |
| --------------------------------- | -------------------------------------------------------------- | ----------------------------------- |
| `PAYMOB_API_KEY`                  | Authenticating with Paymob                                     | All Paymob operations               |
| `PAYMOB_API_SECRET`               | Secret-key requests (intentions, refunds)                       | Subscriptions, refunds              |
| `PAYMOB_PUBLIC_KEY`               | Building the unified-checkout `payLink` for subscriptions        | Subscriptions                       |
| `PAYMOB_INTEGRATION_ID`           | Card integration for iframe payments                            | Paymob card payments                |
| `PAYMOB_MOTO_INTEGRATION_ID`      | MOTO integration used for subscription plans and intentions ( create it throgh paymob dashboard create integration as MIGS )     | Subscriptions                       |
| `PAYMOB_WALLET_INTEGRATION_ID`    | Wallet integration                                              | Paymob wallet payments              |
| `PAYMOB_IFRAME_ID`                | Iframe ID used to build the card checkout URL                    | Paymob card payments                |
| `PAYMOB_SUBSCRIPTION_WEBHOOK_URL` | URL registered with Paymob for subscription lifecycle webhooks   | Subscriptions                       |
| `PAYMOB_HMAC`                     | HMAC secret used to verify Paymob transaction callbacks          | Paymob callbacks                    |
| `PAYMOB_CURRENCY`                 | Currency sent to Paymob (default `EGP`)                          | Paymob payments + subscription intentions |

---

## Stripe

```env
STRIPE_API_SECRET=
STRIPE_API_PUBLISHED_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_BASE_URL=https://api.stripe.com
STRIPE_CURRENCY=USD
```

| Variable                   | Used for                                                        | Read by the package? |
| -------------------------- | --------------------------------------------------------------- | :------------------: |
| `STRIPE_API_SECRET`        | Stripe secret key for all server-side Stripe calls               |          ✅         |
| `STRIPE_WEBHOOK_SECRET`    | Verifying the `Stripe-Signature` header of incoming webhooks      |          ✅         |
| `STRIPE_API_PUBLISHED_KEY` | Publishable key for the frontend / optional Blade card component  |          ❌         |
| `STRIPE_BASE_URL`          | Stripe API base URL (default `https://api.stripe.com`)            |          ❌         |
| `STRIPE_CURRENCY`          | Default currency value defined in the config file                 |          ❌         |

`STRIPE_API_PUBLISHED_KEY`, `STRIPE_BASE_URL`, and `STRIPE_CURRENCY` are defined in the
configuration file but are not read by the package code in this release. The publishable
key is what you pass to the optional Blade component or your own frontend:

```blade
<x-ma-payment::Stripe.card
    :publishable-key="config('ma-payment.STRIPE_API_PUBLISHED_KEY')"
    ...
/>
```

---

## Driver registry

`config('ma-drivers')` maps a driver name to a gateway class:

```php
return [
    'stripe' => \Ma\Payment\Gateways\Stripe\StripeGateway::class,
    'paymob' => \Ma\Payment\Gateways\Paymob\PaymobGateway::class,
];
```

Names are matched case-insensitively (`driver('Paymob')` also works). To add your own
gateway, add an entry here and make sure the class implements `PaymentGatewayInterface` —
see [9. Advanced → Extending gateways](09-advanced.md#extending-gateways).

---

## Minimum configuration per capability

| Capability                | Variables                                                                                          |
| ------------------------- | -------------------------------------------------------------------------------------------------- |
| Paymob card payments      | `PAYMOB_API_KEY`, `PAYMOB_INTEGRATION_ID`, `PAYMOB_IFRAME_ID`, `PAYMOB_HMAC`, `PAYMOB_CURRENCY`      |
| Paymob wallet payments    | the above **plus** `PAYMOB_WALLET_INTEGRATION_ID`                                                   |
| Paymob subscriptions      | `PAYMOB_API_KEY`, `PAYMOB_API_SECRET`, `PAYMOB_PUBLIC_KEY`, `PAYMOB_MOTO_INTEGRATION_ID`, `PAYMOB_SUBSCRIPTION_WEBHOOK_URL`, `PAYMOB_HMAC`, `PAYMOB_CURRENCY` |
| Stripe card payments      | `STRIPE_API_SECRET`                                                                                 |
| Stripe webhooks           | `STRIPE_WEBHOOK_SECRET`                                                                             |

---

## Application-side settings that affect the package

These are not package configuration keys, but the package relies on them:

| Setting                            | Why it matters                                                                                     |
| ---------------------------------- | -------------------------------------------------------------------------------------------------- |
| Queue connection + a queue worker  | `UpdateRefundTransactionJob` and `UpdateSubscriptionJob` implement `ShouldQueue`. Without a worker, refund and subscription state updates never run. |
| Web server / PHP timeouts          | The initial subscription callback can block for up to ~20 seconds while Paymob's subscription record becomes available. |
| CSRF exclusions                    | Callback routes must not require a CSRF token.                                                      |
| Database driver                    | Migrations use standard Laravel schema features (`json`, `enum`, foreign keys) and the test suite runs on SQLite in memory. |

---

## Published assets

| Publish tag             | Publishes                                                     |
| ----------------------- | -------------------------------------------------------------- |
| `ma-payment-config`     | `ma_payment_conf.php` → `config/`                                |
| `ma-payment-migrations` | the package migrations → `database/migrations/`                  |
| `ma-payment-views`      | Blade views → `resources/views/vendor/ma_payment`, JS → `public/js/vendor/ma_payment` |

---

[← Previous: 6. Gateway Architecture](06-gateway-architecture.md) · [Next: 8. Troubleshooting →](08-troubleshooting.md)

