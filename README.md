<p align="center"><img src="./logo.png" width="300"></p>
<p align="center">
    <img alt="GitHub License" src="https://img.shields.io/github/license/allamo123/lara_payments_ma?style=flat&label=license">
    <img alt="Packagist Downloads" src="https://img.shields.io/packagist/dm/ma-lara/payments?style=flat&label=downloads">
    <img alt="GitHub Release" src="https://img.shields.io/github/v/release/allamo123/lara_payments_ma?include_prereleases&style=flat">
</p>

<h1 align="center">MA Lara Payment</h1>

<p align="center">
    A unified Laravel payment package for integrating multiple payment gateways
    through a consistent API, including <strong>Paymob subscriptions</strong>.
</p>

<p align="center">
    <a href="#documentation">Documentation</a>
    &nbsp;&nbsp;•&nbsp;&nbsp;
    <a href="#quick-start">Quick Start</a>
    &nbsp;&nbsp;•&nbsp;&nbsp;
    <a href="#version">Version</a>
    &nbsp;&nbsp;•&nbsp;&nbsp;
    <a href="#testing">Testing</a>
    &nbsp;&nbsp;•&nbsp;&nbsp;
    <a href="#contributing">Contributing</a>
    &nbsp;&nbsp;•&nbsp;&nbsp;
    <a href="#license">License</a>
</p>

---

## What is this package?

**ma-lara/payments** gives Laravel applications one API for multiple payment providers,
plus a subscription API for Paymob:

```php
use Ma\Payment\Facades\MaPayment;

$payment = MaPayment::driver('paymob');

// Payments
$paylink = $payment->pay([...]);
$payment->verify($request->all());
$payment->refund($transactionId, 50);

// Subscriptions
$payment->subscription()->createPlan([...]);
$result = $payment->subscription()->subscribe($plan, $customerData);
$payment->subscription()->lifeCycle($request->all());
```

Provider-specific API calls, authentication, response mapping, and callback handling stay
isolated inside each gateway. The backend is **frontend-agnostic** — an optional Stripe
Blade card component is included but never required.

---

## Capabilities

* Unified gateway contract with runtime driver selection (`MaPayment::driver(...)`).
* Stripe card payments using PaymentIntents, signed webhooks, retries, refunds.
* Paymob card payments (hosted iframe), mobile wallet payments, HMAC callbacks, retries,
  refunds, and **subscriptions**.
* Normalized `PaymentStatus` and `SubscriptionStatus` enums.
* Local persistence of customers, transactions, refunds, saved card records, plans,
  subscriptions, and webhook events.
* Queued jobs for async refund updates and subscription lifecycle updates.
* Extensible architecture for adding gateways and subscription implementations.

**Not implemented:** capture, void, Stripe subscriptions, charging a stored card through
the package API, and persisted recurring (renewal) transactions. See
[1. Introduction](docs/01-introduction.md) for the full list.

---

## Supported gateways

| Gateway | Card | Wallet | Retry | Refund | Webhook / Callback | Subscription |
| ------- | :--: | :----: | :---: | :----: | :----------------: | :----------: |
| Stripe  |  ✅  |   ❌   |  ✅   |   ✅   |     ✅ Signed      |      ❌      |
| Paymob  |  ✅  |   ✅   |  ✅   |   ✅   |      ✅ HMAC       |      ✅      |

Subscriptions are exposed by gateways that implement `SubscrptionableInterface`:

```php
MaPayment::driver('paymob')->subscription();   // ✅
```

---

## Requirements

| Requirement | Version            |
| ----------- | ------------------ |
| PHP         | `>=8.1`            |
| Laravel     | `>=9.0 <14.0`      |
| JSON        | `ext-json`         |
| cURL        | `ext-curl`         |
| Stripe SDK  | `stripe/stripe-php` |

---

## Installation

```bash
composer require ma-lara/payments

php artisan vendor:publish --tag=ma-payment-config
php artisan vendor:publish --tag=ma-payment-migrations
php artisan migrate
```

Then set your provider credentials in `.env` and expose your own callback routes —
the package does not register routes for you.

Full instructions, including the queue worker requirement and the subscription webhook
URL: **[2. Installation](docs/02-installation.md)**.

---

## Quick Start

```php
use Ma\Payment\Facades\MaPayment;

Route::post('/pay', function (Illuminate\Http\Request $request) {
    $paymob = MaPayment::driver('paymob');

    $paylink = $paymob->pay([
        'amount' => 150.50,          // major units
        'currency' => 'EGP',
        'customer' => [
            'id' => auth()->id(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '01010101010',
        ],
        'source' => 'card',          // or 'wallet'
    ]);

    return redirect($paylink);
});
```

```php
// Provider callback (your own route)
Route::post('/paymob/callback', function (Illuminate\Http\Request $request) {
    return response()->json(
        MaPayment::driver('paymob')->verify($request->all())
    );
});
```

More: **[3. Quick Start](docs/03-quick-start.md)** — including a minimal subscription
example.

---

## Documentation

The documentation is organized by what you are trying to do. Start here:

| # | Chapter                                                       | You will find |
| - | ------------------------------------------------------------- | ------------- |
| 1 | [Introduction](docs/01-introduction.md)                         | What the package does, capabilities, supported gateways, requirements, what is intentionally not implemented |
| 2 | [Installation](docs/02-installation.md)                         | Composer install, configuration, migrations, callback routes, subscription webhook URL, queue worker |
| 3 | [Quick Start](docs/03-quick-start.md)                           | The minimum steps for a normal payment and for a Paymob subscription |
| 4 | [Payments](docs/04-payments.md)                                 | Creating a Payment · Payment Response · Payment Status · Payment Callbacks / Webhooks · Retry Payment · Refunds · Transactions · Saved Cards |
| 5 | [Subscriptions](docs/05-subscriptions.md)                       | Subscription Overview · Creating a Subscription Plan · Listing Plans · Updating a Plan · Suspending/Resuming a Plan · Creating a Subscription · Subscription Lifecycle · Updating a Subscription · Subscription Callbacks / Webhooks · Subscription Transactions |
| 6 | [Gateway Architecture](docs/06-gateway-architecture.md)         | How gateways are resolved, the public contracts, and `$payment->subscription()` |
| 7 | [Configuration](docs/07-configuration.md)                       | Every config file, environment variable, publish tag, and capability requirement |
| 8 | [Troubleshooting](docs/08-troubleshooting.md)                   | Real errors thrown by the package and how to resolve them |
| 9 | [Advanced / Developer Documentation](docs/09-advanced.md)       | Architecture, DTOs, value objects, repositories, webhook internals, testing, extending gateways, adding another subscription implementation |

A short answer for the most common questions:

| Question                                    | Answer |
| ------------------------------------------- | ------ |
| How do I install the package?               | [2. Installation](docs/02-installation.md) |
| How do I make a payment?                    | [3. Quick Start](docs/03-quick-start.md) → [4. Payments](docs/04-payments.md) |
| How do I create a subscription plan?        | [5. Subscriptions → Creating a Subscription Plan](docs/05-subscriptions.md#creating-a-subscription-plan) |
| How do I create a subscription?             | [5. Subscriptions → Creating a Subscription](docs/05-subscriptions.md#creating-a-subscription) |
| How do I suspend or resume it?              | [5. Subscriptions → Subscription Lifecycle](docs/05-subscriptions.md#subscription-lifecycle) |
| How do refunds, retries, and callbacks work? | [4. Payments](docs/04-payments.md) |
| Where do I find implementation details?     | [9. Advanced / Developer Documentation](docs/09-advanced.md) |

### Key subscription methods at a glance

```php
$payment = MaPayment::driver('paymob');

$payment->subscription()->createPlan([...]);          // create a plan (local + gateway)
$payment->subscription()->listPlans();                // local plans
$payment->subscription()->findPlanByLocalId($id);     // local plan
$payment->subscription()->updateSubscriptionPlan($gatewayPlanId, [...]);
$payment->subscription()->suspendPlan($gatewayPlanId);
$payment->subscription()->resumePlan($gatewayPlanId);

$payment->subscription()->subscribe($plan, $customerData);   // initial payment
$payment->subscription()->paginateLocalSubscrptions(15);     // local subscriptions
$payment->subscription()->findSubscrptionByLocalId($id);     // local subscription
$payment->subscription()->suspendSubscription($gatewaySubscriptionId);
$payment->subscription()->resumeSubscription($gatewaySubscriptionId);
$payment->subscription()->updateGatewaySubscription($gatewaySubscriptionId, [...]);
$payment->subscription()->lifeCycle($request->all());        // lifecycle webhook
```

> Method names are reproduced exactly as implemented (`findSubscrptionByLocalId`,
> `paginateLocalSubscrptions`). `$gatewayPlanId` / `$gatewaySubscriptionId` are Paymob
> IDs; the `...ByLocalId` methods take local database IDs.

---

## Version

These documents describe **v2.1.0**.

`v2.1.0` is a **backward-compatible feature release** that introduces Paymob subscription
support. Existing payment behaviour, public methods, configuration keys, and previously
existing database tables are unchanged.

Schema additions in this release:

* new tables: `subscription_plans`, `subscriptions`, `subscription_webhook_events`,
  `customer_cards`
* two nullable columns on `payment_transactions`: `subscription_id`,
  `subscription_transaction_type`

Only version references related to this release were updated. Historical release notes
are not maintained in this repository.

---

## Testing

```bash
composer test
composer test -- --testdox
```

* PHPUnit treats `tests/Unit` and `tests/Feature` as separate suites (`phpunit.xml`).
* Tests run against SQLite `:memory:` using Orchestra Testbench
  (`tests/TestCase.php` registers the provider and migrates the package migrations).
* Existing tests cover the Paymob payment happy path, the failed-payment path, and the
  payment DTOs. **Subscription behaviour is not covered by automated tests yet** —
  see [9. Advanced → Testing](docs/09-advanced.md#testing).
* CI runs the suite on a PHP/Laravel matrix (`.github/workflows/test.yaml`), and a local
  `pre-push` hook (`.github/hooks/pre-push`) runs `composer test` before pushing.

---

## Contributing

Contributions are welcome.

When adding or modifying functionality:

1. follow the existing architecture;
2. keep gateway-specific code inside its gateway directory;
3. avoid changing the shared payment workflow unnecessarily;
4. add or update tests and run `composer test`;
5. update the [documentation](docs/README.md) and the capability tables;
6. preserve backward compatibility.

---

## Security

* Never commit provider secrets — use `.env` and keep the config cache cleared after
  changes.
* The package does not register routes; you own the callback endpoints. Exclude them from
  CSRF protection where needed.
* Stripe webhooks are signature-verified. Paymob payment callbacks are HMAC-verified.
  Paymob subscription **lifecycle** webhooks are not signature-verified by the package —
  protect that endpoint in your application (see
  [5. Subscriptions → Subscription Callbacks / Webhooks](docs/05-subscriptions.md#subscription-callbacks--webhooks)).
* Report security issues privately to the author rather than in a public issue.

---

## Author

[![Mohamed Allam](https://github.com/allamo123.png?size=90)](https://github.com/allamo123)

## License

MIT © [Mohamed Allam](https://github.com/allamo123)


