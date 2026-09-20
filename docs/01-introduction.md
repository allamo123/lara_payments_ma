# 1. Introduction

[← Documentation index](README.md)

---

## What is this package?

**ma-lara/payments** is a Laravel package that provides a **unified API for multiple
payment gateways** and, since **v2.1.0**, a unified API for **Paymob subscriptions**.

Instead of calling each provider SDK directly, your application resolves a gateway and
uses one consistent set of methods:

```php
use Ma\Payment\Facades\MaPayment;

$gateway = MaPayment::driver('paymob');

$paylink = $gateway->pay([...]);       // start a payment
$gateway->verify($request->all());     // handle a provider callback
$gateway->refund($transactionId, 50);  // refund
```

Each gateway keeps its provider-specific API calls, authentication, response mapping,
and callback handling isolated inside its own namespace. Your application code does not
need to change when you switch gateways.

The package is **frontend-agnostic**. It returns a payment URL / redirect or a payment
result, so you can use it from Blade, React, Vue, Angular, vanilla JavaScript, or a
mobile application. An optional Stripe Blade card component is included, but it is
never required.

---

## Supported payment capabilities

| Capability                            | Documented in                                                |
| ------------------------------------- | ------------------------------------------------------------ |
| Card payments                         | [4. Payments](04-payments.md)                                 |
| Hosted card checkout (iframe)         | [4. Payments → Creating a Payment](04-payments.md)            |
| Mobile wallet payments (Paymob)       | [4. Payments → Creating a Payment](04-payments.md)            |
| Payment status normalization          | [4. Payments → Payment Status](04-payments.md)                |
| Callbacks / webhooks                  | [4. Payments → Payment Callbacks / Webhooks](04-payments.md)  |
| Payment retries                       | [4. Payments → Retry Payment](04-payments.md)                 |
| Full and partial refunds              | [4. Payments → Refunds](04-payments.md)                       |
| Transaction listing / filtering       | [4. Payments → Transactions](04-payments.md)                  |
| Saved card records (Paymob `TOKEN`)   | [4. Payments → Saved Cards](04-payments.md)                   |
| **Subscriptions (Paymob)**            | [5. Subscriptions](05-subscriptions.md)                       |

### Capabilities that are **not** implemented

Listed here so you do not go looking for them:

| Capability                                      | Status                                                                    |
| ----------------------------------------------- | ------------------------------------------------------------------------- |
| Capture / authorize-then-capture                | Not implemented in any gateway.                                           |
| Void                                            | Not implemented. Paymob refunds are treated as refunds.                   |
| Stripe subscriptions                            | Not implemented. Only Paymob implements the subscription contract.        |
| Listing **gateway** subscription plans publicly | Not exposed through the public package API (see [5. Subscriptions](05-subscriptions.md)). |
| Charging a stored card from the package API     | Not implemented. The package only stores card records.                    |
| Persisting recurring (renewal) transactions     | Not implemented. See [5. Subscriptions](05-subscriptions.md).             |

---

## Supported gateways

| Gateway | Card | Wallet | Retry | Refund | Webhook / Callback | Subscription |
| ------- | :--: | :----: | :---: | :----: | :----------------: | :----------: |
| Stripe  |  ✅  |   ❌   |  ✅   |   ✅   |     ✅ Signed      |      ❌      |
| Paymob  |  ✅  |   ✅   |  ✅   |   ✅   |      ✅ HMAC       |      ✅      |

* **Stripe** — card payments through Stripe PaymentIntents, signed webhooks, refunds,
  and retries.
* **Paymob** — card payments through a hosted Paymob iframe, mobile wallet payments,
  HMAC callbacks, refunds, retries, and **subscriptions**.

The driver registry lives in [`config/ma_payment_drivers.php`](../config/ma_payment_drivers.php).
A driver is selected at runtime:

```php
use Ma\Payment\Facades\MaPayment;

$stripe = MaPayment::driver('stripe');
$paymob = MaPayment::driver('paymob');
```

Selecting a gateway that is not registered throws:

```text
Payment gateway [<driver>] does not exist
```

---

## Supported subscription functionality

Subscriptions are currently implemented **for Paymob only**, and are reached through
`$payment->subscription()`:

```php
use Ma\Payment\Facades\MaPayment;

$payment = MaPayment::driver('paymob');

$payment->subscription()->listPlans();
```

| Subscription capability                    | Public method                                                       |
| ------------------------------------------ | ------------------------------------------------------------------- |
| Create a subscription plan                 | `$payment->subscription()->createPlan([...])`                        |
| Find a **local** plan by local ID          | `$payment->subscription()->findPlanByLocalId($id)`                   |
| List **local** plans                       | `$payment->subscription()->listPlans()`                              |
| Update a plan (amount, number of deductions) | `$payment->subscription()->updateSubscriptionPlan($planId, [...])` |
| Suspend a plan                             | `$payment->subscription()->suspendPlan($planId)`                     |
| Resume a plan                              | `$payment->subscription()->resumePlan($planId)`                      |
| Start a subscription (initial payment)     | `$payment->subscription()->subscribe($plan, $customerData)`           |
| List **local** subscriptions (paginated)   | `$payment->subscription()->paginateLocalSubscrptions($perPage)`       |
| Find a **local** subscription by local ID  | `$payment->subscription()->findSubscrptionByLocalId($id)`             |
| Suspend a subscription                     | `$payment->subscription()->suspendSubscription($subscriptionId)`     |
| Resume a subscription                      | `$payment->subscription()->resumeSubscription($subscriptionId)`      |
| Update a subscription at the gateway       | `$payment->subscription()->updateGatewaySubscription($id, [...])`    |
| Process subscription lifecycle callbacks   | `$payment->subscription()->lifeCycle($payload)`                      |

`$planId` and `$subscriptionId` are **gateway** identifiers (Paymob IDs), while
`findPlanByLocalId()` and `findSubscrptionByLocalId()` take **local** database IDs.
This distinction is explained in [5. Subscriptions](05-subscriptions.md).

> The method names above are the exact names used by the current implementation,
> including their spelling (`findSubscrptionByLocalId`, `paginateLocalSubscrptions`).

---

## What this package is intended for

Use this package when you need:

* One consistent payment API in a Laravel application instead of provider-specific code.
* Paymob subscriptions (plans, initial payments, lifecycle callbacks, local subscription state).
* Local persistence of customers, transactions, refunds, cards, plans, and subscriptions.
* Normalized payment statuses across gateways.
* An extensible gateway architecture you can add providers to.

The package is **not** a frontend checkout library, a billing/invoicing system, or an
accounting tool. Recurring charges themselves are executed by Paymob; the package
integrates with them and mirrors their state locally.

---

## Requirements

Requirements are defined by [`composer.json`](../composer.json):

| Requirement | Version             |
| ----------- | ------------------- |
| PHP         | `>=8.1`             |
| Laravel     | `>=9.0 <14.0`       |
| JSON        | `ext-json`          |
| cURL        | `ext-curl`          |
| Stripe SDK  | `stripe/stripe-php` |

Laravel package discovery registers the service provider and the `MaPayment` facade
automatically.

---

## Version

This documentation describes **v2.1.0**, a backward-compatible feature release that
adds Paymob subscription support. See the [documentation index](README.md#version) for
details.

---

[← Documentation index](README.md) · [Next: 2. Installation →](02-installation.md)


