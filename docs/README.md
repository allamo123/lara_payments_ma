# Documentation

Welcome to the **ma-lara/payments** documentation.

This documentation describes the current implementation of the package, including
**Paymob subscriptions** introduced in **v2.1.0**.

Start here, then follow the section that matches what you are trying to do.

---

## Where do I start?

| I want to…                                   | Read                                           |
| -------------------------------------------- | ---------------------------------------------- |
| Understand what the package does             | [1. Introduction](01-introduction.md)          |
| Install and configure it                     | [2. Installation](02-installation.md)          |
| Make my first payment as fast as possible    | [3. Quick Start](03-quick-start.md)            |
| Learn everything about payments              | [4. Payments](04-payments.md)                  |
| Create subscription plans and subscriptions  | [5. Subscriptions](05-subscriptions.md)        |
| Understand how gateways are structured       | [6. Gateway Architecture](06-gateway-architecture.md) |
| See every configuration key and env variable | [7. Configuration](07-configuration.md)        |
| Fix something that is not working            | [8. Troubleshooting](08-troubleshooting.md)    |
| Work on the package itself (internals, tests) | [9. Advanced / Developer Documentation](09-advanced.md) |

---

## Section overview

### [1. Introduction](01-introduction.md)

What the package is, which capabilities exist today, which gateways are supported,
and what the package is intentionally **not** trying to be.

### [2. Installation](02-installation.md)

Composer installation, service provider registration, publishing the configuration,
views, and migrations, and running the package migrations.

### [3. Quick Start](03-quick-start.md)

The minimum number of steps required to make a normal payment, and the minimum steps
required to create a subscription plan and a subscription.

### [4. Payments](04-payments.md)

* Creating a Payment
* Payment Response
* Payment Status
* Payment Callbacks / Webhooks
* Retry Payment
* Refunds
* Transactions
* Saved Cards

### [5. Subscriptions](05-subscriptions.md)

* Subscription Overview (Subscription Plan, Subscription, Customer, Initial Transaction, Recurring Billing, Lifecycle)
* Creating a Subscription Plan
* Listing Subscription Plans
* Updating a Subscription Plan
* Suspending and Resuming a Subscription Plan
* Creating a Subscription
* Subscription Lifecycle
* Updating a Subscription
* Subscription Callbacks / Webhooks
* Subscription Transactions

### [6. Gateway Architecture](06-gateway-architecture.md)

The public architecture only: how a gateway exposes payments and how it exposes
subscriptions through `$payment->subscription()`.

### [7. Configuration](07-configuration.md)

Every configuration file, environment variable, and published asset in one place.

### [8. Troubleshooting](08-troubleshooting.md)

Real errors thrown by the package and what to check for each one.

### [9. Advanced / Developer Documentation](09-advanced.md)

Architecture, DTOs, value objects, repositories, webhook processing internals,
testing, extending the package with a new gateway, and adding another subscription
implementation.

---

## Conventions used in this documentation

| Term                  | Meaning                                                                          |
| --------------------- | -------------------------------------------------------------------------------- |
| Gateway               | A payment provider driver resolved through `MaPayment::driver(...)`.             |
| `$gateway` / `$payment` | The object returned by `MaPayment::driver('stripe' \| 'paymob')`.              |
| Transaction           | A local `payment_transactions` row for a single payment attempt.                 |
| Callback / Webhook    | An HTTP request sent by the gateway to a route **owned by your application**.    |
| Local data            | Data stored by the package in your application database.                         |
| Gateway data          | Data that lives at the provider (Stripe / Paymob).                               |

Whenever both a local and a gateway concept exist, both are labelled explicitly
(for example **local subscription plan** vs **gateway subscription plan**).

---

## Version

These documents describe **v2.1.0**.

`v2.1.0` is a **backward-compatible feature release** that adds Paymob subscription
support. Existing payment behaviour, public methods, configuration keys, and database
tables used by earlier `v2.x` releases are unchanged.

The only schema additions in this release are new tables
(`subscription_plans`, `subscriptions`, `subscription_webhook_events`, `customer_cards`)
and two nullable columns on `payment_transactions`
(`subscription_id`, `subscription_transaction_type`).

> Only version references related to this release were updated. Historical release
> notes are not maintained in this repository.

---

[← Back to the main README](../README.md)
