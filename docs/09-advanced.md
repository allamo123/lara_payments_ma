# 9. Advanced / Developer Documentation

[← Documentation index](README.md)

This chapter is for developers who need to **work on the package itself**: internals,
internals of subscription processing, tests, and extension points.

If you only want to *use* the package, read
[1–8](README.md) instead — you do not need anything on this page.

**Contents**

* [Architecture](#architecture)
* [Design patterns](#design-patterns)
* [Core components](#core-components)
* [Subscription internals](#subscription-internals)
* [DTOs](#dtos)
* [Value objects](#value-objects)
* [Repositories](#repositories)
* [Webhook processing internals](#webhook-processing-internals)
* [Database relationships](#database-relationships)
* [Testing](#testing)
* [Extending gateways](#extending-gateways)
* [Adding another subscription implementation](#adding-another-subscription-implementation)
* [Project structure](#project-structure)

---

## Architecture

```text
Application
     │
     ▼
MaPayment Facade
     │
     ▼
PaymentGatewayManager
     │
     ▼
PaymentGatewayFactory   ← config('ma-drivers')
     │
     ├──────────────────────────────┐
     ▼                              ▼
StripeGateway                  PaymobGateway
     │                              │  └── implements SubscrptionableInterface
     ▼                              │         └── PaymobSubscription
StripeApiService                    ▼
StripeWebhookHandler           PaymobApiService
     │                         PaymobTransactionCallbackHandler
     ▼                         PaymobSubscriptionCallbackHandler
Stripe API                     PaymobSubscriptionWebhookHandler
     │                              │
     └──────────────┬───────────────┘
                    ▼
             BaseGateway (shared payment orchestration)
                    │
                    ▼
           Repositories → Eloquent models → database
```

`BaseGateway::executePayment()` implements the shared payment workflow:

```text
PaymentRequestDTO
      ↓
Customer handling (local customer get-or-create)
      ↓
Gateway customer handling (gateway-specific hook)
      ↓
Gateway API request (sendPaymentRequest)
      ↓
PaymentTransactionDTO (buildPaymentTransactionDTO)
      ↓
TransactionRepository (create or update)
```

Gateway classes must **not** duplicate this workflow; they implement
`sendPaymentRequest()` and `buildPaymentTransactionDTO()`.

---

## Design patterns

| Pattern         | Where                                                                  |
| --------------- | ---------------------------------------------------------------------- |
| Facade          | `Ma\Payment\Facades\MaPayment`                                          |
| Factory         | `Ma\Payment\Factories\PaymentGatewayFactory`                            |
| Strategy        | `PaymentGatewayInterface` implemented by each gateway                   |
| Template Method | `BaseGateway::executePayment()` + abstract gateway hooks                |
| Adapter         | `PaymobSubscription` adapts `SubscriptionInterface` to `PaymobSubscriptionService` |
| Repository      | `TransactionRepository`, `PaymentCustomerRepository`, `RefundTransactionRepository`, `SubscriptionPlanRepository`, `SubscriptionRepository`, `SubscriptionWebhookEventRepository` |
| DTO             | `PaymentRequestDTO`, `PaymentTransactionDTO`, `PaymobSubscriptionPlanRequestDTO`, `PaymobSubscriptionPlanResponseDTO` |
| Value Object    | `Money`, `UserEmail`, `UserId`                                          |
| Job / Queue     | `UpdateRefundTransactionJob`, `UpdateSubscriptionJob`                   |

---

## Core components

### `MaPaymentServiceProvider`

* merges `config/ma_payment_conf.php` as `ma-payment` and `config/ma_payment_drivers.php`
  as `ma-drivers`
* binds `TransactionRepositoryInterface` → `TransactionRepository`
* binds `PaymobApiService` (injecting `ClientApiService`)
* registers the Blade view namespace `ma-payment` and publishes config, views, JS, and
  migrations

### `PaymentGatewayManager` and `PaymentGatewayFactory`

`driver($name)` delegates to the factory, which:

1. lowercases the name,
2. looks it up in `config('ma-drivers')`,
3. throws when the driver is unknown or does not implement `PaymentGatewayInterface`,
4. resolves the instance through Laravel's container (`app($class)`).

### `ClientApiService`

Thin HTTP helper used by the Paymob API service:

* `post()`, `put()`, `get()` — JSON requests, `verify` disabled
* `postWithSecretKey()` — adds `Authorization: Token <secret>` and **throws** on HTTP
  failure (`->throw()`)

---

## Subscription internals

> Reminder: these classes are implementation details. Applications should only use
> `$payment->subscription()` — see
> [6. Gateway Architecture](06-gateway-architecture.md).

### Layering

```text
PaymobGateway
   │  implements SubscrptionableInterface
   ▼
PaymobSubscription                 ← adapter implementing SubscriptionInterface
   │
   ├── PaymobSubscriptionService   ← orchestration: gateway calls + local persistence
   │        ├── PaymobApiService   ← Paymob HTTP endpoints
   │        ├── SubscriptionPlanRepository
   │        ├── SubscriptionRepository
   │        ├── TransactionRepository
   │        └── CustomerSerivce
   │
   └── PaymobSubscriptionWebhookHandler ← lifecycle webhook entry point
            ├── SubscriptionWebhookEventRepository
            └── UpdateSubscriptionJob (queued)
```

`PaymobGateway::verify()` uses two callback handlers:

* `PaymobTransactionCallbackHandler` — normal payment callbacks (HMAC verified)
* `PaymobSubscriptionCallbackHandler` — subscription transaction callbacks
  (selected when the payload contains `intention`)

### Paymob endpoints used by the subscription feature

Base URL: `https://accept.paymobsolutions.com/api` (authentication-token requests), with
secret-key requests against `https://accept.paymob.com/v1/intention/`.

| Operation                     | Endpoint                                                    |
| ----------------------------- | ----------------------------------------------------------- |
| Authentication token          | `POST /auth/tokens`                                          |
| Create plan                   | `POST /acceptance/subscription-plans`                        |
| Update plan                   | `PUT /acceptance/subscription-plans/{planId}`                |
| List plans (internal helper)  | `GET /acceptance/subscription-plans`                         |
| Suspend / resume plan         | `POST /acceptance/subscription-plans/{planId}/suspend` · `/resume` |
| Create subscription intention | `POST https://accept.paymob.com/v1/intention/` (secret key)   |
| List subscriptions (internal) | `GET /acceptance/subscriptions?page={page}`                   |
| Find subscription (internal)  | `GET /acceptance/subscriptions/{id}`                          |
| Find subscription by transaction (used by `verify()`) | `GET /acceptance/subscriptions?transaction={transactionId}` |
| Subscription transactions (internal) | `GET /acceptance/subscriptions/{id}/transactions`      |
| Card tokens (internal)        | `GET /acceptance/subscriptions/{id}/card-tokens`              |
| Register webhook              | `POST /acceptance/subscriptions/{id}/register_webhook`        |
| Suspend / resume subscription | `POST /acceptance/subscriptions/{id}/suspend` · `/resume`     |
| Update subscription           | `PUT /acceptance/subscriptions/{id}`                          |

"Internal" means the method exists on `PaymobApiService` / `PaymobSubscriptionService`
but is **not** exposed through `SubscriptionInterface`.

### Idempotency and retries

`subscription_webhook_events` stores one row per `(gateway, event_id)`. A re-delivered
event finds the existing row; if `processed_at` is already set, the handler returns
without dispatching anything. `UpdateSubscriptionJob` runs inside a database transaction,
updates the local subscription, and marks the event processed; after `$tries = 3`
failures `failed()` records the exception message in `failure_reason`.

---

## DTOs

### `PaymentRequestDTO`

Boundary: **Application → Package**. Represents a validated payment request.

| Property              | Type                | Source                                    |
| --------------------- | ------------------- | ------------------------------------------ |
| `gateway`             | string              | Set by `BaseGateway` from the gateway name  |
| `amount`              | `Money`             | `amount` (major units)                      |
| `currency`            | string              | `currency`                                  |
| `user_id`             | `UserId`            | `customer.id`                               |
| `user_first_name`     | string              | `customer.first_name`                       |
| `user_last_name`      | string              | `customer.last_name`                        |
| `user_email`          | `UserEmail`         | `customer.email`                            |
| `user_phone`          | string              | `customer.phone`                            |
| `source`              | string              | `source`                                    |
| `gateway_customer_id` | ?string             | `customer.gateway_customer_id` (optional)   |
| `payment_method`      | ?array              | `payment_method` (optional, Stripe)         |

Helpers: `customer()` (local customer payload), `customerApi()` (gateway customer
payload), `paymentData()` (gateway payment payload), `attachGatewayCustomerId()`.

### `PaymentTransactionDTO`

Boundary: **Gateway → Persistence**.

`fromArray()` requires a non-empty `gateway`, `amount`, `locale_customer_id`, `source`,
`payment_status`, and `currency`; `source_subtype`, `orderId`, and `gateway_reference`
are optional. `toDatabase()` returns the columns for `payment_transactions`, including
`minor_amount` (`amount->toCents()`) and `meta_data` (`json_encode` of the raw response).

Validation happens through the value objects:

* empty gateway → `InvalidArgumentException("Not valid gatway name")`
* `amount <= 0` → `InvalidArgumentException('Amount not valid it must be > 0')`
* `locale_customer_id <= 0` → `InvalidArgumentException("User ID cannot less than zero")`

### `PaymobSubscriptionPlanRequestDTO`

Boundary: **Application → Paymob** for plan creation/update.

`fromArray()` requires `frequency`, `name`, `reminder_days`, `retrial_days`, `amount`,
and the presence of the `number_of_deductions` key; `planType`, `use_transaction_amount`,
and `is_active` have defaults (`rent`, `false`, `true`). `apiRequestData()` produces the
Paymob payload:

```php
[
    'frequency', 'name', 'reminder_days', 'retrial_days',
    'plan_type', 'amount_cents', 'use_transaction_amount',
    'is_active', 'number_of_deductions',
]
```

### `PaymobSubscriptionPlanResponseDTO`

Boundary: **Paymob → Local persistence**.

`toDatabase()` maps the Paymob response into the local `subscription_plans` row:

| Local column              | Paymob response field   |
| ------------------------- | ----------------------- |
| `gateway`                 | hard-coded `paymob`     |
| `gateway_plan_id`         | `id`                    |
| `name`                    | `name`                  |
| `minor_amount`            | `amount_cents`          |
| `billing_interval_count`  | `frequency`             |
| `billing_cycles`          | `number_of_deductions`  |
| `is_active`               | `is_active`             |
| `metadata`                | the full response (JSON) |

`reminder_days`, `retrial_days`, `planType`, and `use_transaction_amount` are read by the
DTO but are **not** persisted locally; they are available inside `metadata`.

---

## Value objects

| Value object | Validation                                | Notes |
| ------------ | ----------------------------------------- | ----- |
| `Money`      | `amount > 0`, else `InvalidArgumentException('Amount not valid it must be > 0')` | `value()` returns the raw amount, `toCents()` multiplies by 100, `add()` adds to itself (not to an argument), `toPounds()` divides by 100 and formats with `number_format()` using **0 decimals**, so it rounds to whole units and is not suitable for exact display. |
| `UserEmail`  | `preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', ...)`, else `InvalidArgumentException('Invalid email')` | Used for the customer email |
| `UserId`     | `userId > 0`, else `InvalidArgumentException("User ID cannot less than zero")` | Used for the application user ID |

`PaymentTransactionDTO::toDatabase()` stores `minor_amount` from `Money::toCents()`, so
`Money` is normally constructed with **major units** for payments. In the subscription
plan response DTO, `Money` wraps the already-minor `amount_cents` value and only
`value()` is used, so no conversion happens there.

---

## Repositories

| Repository                           | Responsibility                                                                 |
| ------------------------------------ | ------------------------------------------------------------------------------ |
| `TransactionRepository`              | Payment transaction persistence/queries: `findByLocaleId()`, `getAll($status)`, `getTransactionByOrderId()` (row-locked), `getTransactionByRef()`, `getTransactionByGateway()`, `LockUpdateTransactionByRefrence()`, `createOrUpdate($id, $data)`, `updateByOrderId()` |
| `PaymentCustomerRepository`          | Customer mapping: `findCustomer($userId)`, `getCustomerByEmail()`, `createCustomer()`, `updateCustomer()`, `getCustomertTransactions()` |
| `RefundTransactionRepository`        | Refunds: `getRefundTransaction($transactionId)` (row-locked), `createRefundTransaction()` |
| `SubscriptionPlanRepository`         | Plans: `findPlanByLocalId()`, `findLocalPlanByGatewayId()`, `findPlanByName()`, `create()`, `update($gatewayPlanId, $data)`, `listPlans()` |
| `SubscriptionRepository`             | Subscriptions: `findByGatewayId()` (row-locked), `findById()`, `paginateSubscrptions($gateway, $perPage)`, `update($gatewaySubscriptionId, $data)`, `create()` |
| `SubscriptionWebhookEventRepository` | Webhook log: `findById()`, `findOrCreate()`, `markAsProcessed()`, `markAsFailed()` |

Row locking (`lockForUpdate()` followed by an update) is used where concurrent callback
processing could otherwise race: transaction lookup by order ID, refund lookup, and
subscription lookup by gateway ID.

`SubscriptionPlanRepository::update()` and `SubscriptionRepository::update()` both take
the **gateway** identifier, because both call sites work with Paymob IDs.

---

## Webhook processing internals

### Stripe

```text
HTTP request (raw body + Stripe-Signature)
   │
   ▼
StripeGateway::verify(string $payload, ?string $signature)
   │   ├── InvalidArgumentException when payload is not a string
   │   └── InvalidArgumentException when signature is missing
   ▼
StripeWebhookHandler::handle()  (Webhook::constructEvent with STRIPE_WEBHOOK_SECRET)
   │   └── unhandled event → ['handled' => false, 'event_type' => ...]
   ▼
match on event type
   ├── payment_intent.succeeded | payment_intent.payment_failed | payment_intent.canceled
   │        → transaction id + mapped PaymentStatus
   ├── refund.created  → refund id/amount/currency/status
   └── charge.refunded → parent refund state + amounts
   ▼
StripeGateway::verify() inside a DB transaction
   ├── transaction looked up by gateway_reference (TransactionNotFoundException otherwise)
   ├── refund.created  → RefundTransactionRepository::createRefundTransaction()
   └── charge.refunded → parent transaction update + UpdateRefundTransactionJob
                         when a refund id is present
```

The refund lookup-based race handling is explained in
[4. Payments → Payment Callbacks / Webhooks](04-payments.md).

### Paymob (normal payments)

```text
HTTP request
   │
   ▼
PaymobGateway::verify(array|string $payload)
   │   └── JSON string payloads are decoded
   ▼
intention key present?
   ├── no  → PaymobTransactionCallbackHandler::handle()
   │           ├── type TRANSACTION → HMAC verification (throws on mismatch)
   │           ├── type TOKEN       → card details (saved card flow)
   │           └── other type       → RuntimeException('type not exist at webhook handler')
   └── yes → PaymobSubscriptionCallbackHandler::handle()
               └── returns ['type' => 'TRANSACTION', 'source' => 'subscription', ...]
   ▼
card token?  → CustomerSerivce::SaveNewCard() and return
   ▼
normal transaction → find by order id → update inside DB transaction
subscription transaction → verify against Paymob → create local subscription → link transaction
```

### Paymob subscription lifecycle webhooks

```text
HTTP request
   │
   ▼
PaymobSubscription::lifeCycle(array $payload)
   ▼
PaymobSubscriptionWebhookHandler::handle()
   │   match($payload['trigger_type'])
   │     resumed | suspended | cancelled | updated  → handleUpdateSubscription()
   │     default → Log::info() only
   ▼
SubscriptionWebhookEventRepository::findOrCreate(gateway, paymob_request_id, ...)
   │   └── processed_at set?  → stop
   ▼
UpdateSubscriptionJob::dispatch($payload, $event->id)
   ▼
(queued) DB transaction
   ├── update the local subscription by gateway_subscription_id
   └── SubscriptionWebhookEventRepository::markAsProcessed()
   ▼
on final failure → markAsFailed($event, $exception->getMessage())
```

---

## Database relationships

```text
payment_customers
   │ id
   ├──< payment_transactions.customer_id
   │        │ gateway_reference
   │        └──< refunded_payment_transactions.parent_transaction
   ├──< customer_cards.customer_id
   └──< subscriptions.customer_id

subscription_plans
   │ id
   └──< subscriptions.plan_id

subscriptions
   │ id
   └──< payment_transactions.subscription_id   (nullable, nullOnDelete)

subscription_webhook_events   (standalone log table, unique on gateway + event_id)
```

* A customer can have many transactions, cards, and subscriptions.
* A transaction can have many refund records, and can belong to at most one subscription.
* A subscription belongs to one customer and one plan.
* Deleting a customer cascades to its subscriptions and transactions; deleting a
  subscription sets `payment_transactions.subscription_id` to `null`.
* `refunded_payment_transactions.parent_transaction` is a foreign key to
  `payment_transactions.gateway_reference`.

---

## Testing

### Current test suite

```bash
composer test                      # runs phpunit
composer test -- --testdox         # readable output
vendor/bin/phpunit --filter payment
```

Current coverage (all passing):

| Suite   | File                                          | What it covers |
| ------- | --------------------------------------------- | -------------- |
| Feature | `tests/Feature/Paymob/SuccessfulPaymentTest.php` | Paymob card `pay()` happy path with `Http::fake()`; asserts the auth → order → payment-key call order and the returned iframe URL |
| Feature | `tests/Feature/Paymob/FailedPaymentTest.php`     | Paymob `pay()` when the order status is `failed`; asserts the local transaction is persisted as `failed` and amounts are stored in minor units |
| Unit    | `tests/Unit/Paymob/PaymentRequestDTOTest.php`    | `PaymentRequestDTO::fromArray()` mapping + invalid amount (`InvalidArgumentException`) |
| Unit    | `tests/Unit/Paymob/PaymentTransactionDTOTest.php` | `PaymentTransactionDTO` mapping, `toDatabase()`, and invalid amount / customer ID / gateway name |

**There are currently no automated tests for the subscription feature.** If you are
extending subscriptions, treat the behaviours in
[5. Subscriptions](05-subscriptions.md) as the specification to test.

### Test infrastructure

| Component                 | Location / detail                                                                 |
| ------------------------- | --------------------------------------------------------------------------------- |
| PHPUnit configuration     | `phpunit.xml` — suites `Unit` (`tests/Unit`) and `Feature` (`tests/Feature`), SQLite `:memory:` |
| Base test case            | `tests/TestCase.php` — Orchestra Testbench; registers `MaPaymentServiceProvider`, sets the `ma-payment` config, and migrates the package migrations |
| Autoloading               | `Tests\` → `tests/` (`autoload-dev` in `composer.json`)                              |
| PHPUnit version           | `phpunit/phpunit: ^10.0 \|\| ^11.0 \|\| ^12.0` (CI installs a version per matrix entry) |
| Payment API faking        | Laravel's `Http::fake()` / `Http::assertSent()` (used by the existing Paymob tests) |

### Local pre-push hook and CI

A Git `pre-push` hook is provided at `.github/hooks/pre-push`. It runs `composer test` on
every push and rejects the push when the test command fails. It only runs whatever the
test suite contains at that moment; it is a workflow guard, not coverage.

`.github/workflows/test.yaml` runs `composer test -- --testdox` on pushes to `main` and
on pull requests targeting `main`:

| PHP   | Laravel | Testbench | PHPUnit    |
| ----- | ------- | --------- | ---------- |
| 8.1   | 9.*     | ^7.0      | ^9.5       |
| 8.2   | 10.*    | ^8.0      | ^10.0      |
| 8.3   | 11.*    | ^9.0      | ^11.0      |
| 8.3   | 12.*    | ^10.0     | ^11.5      |
| 8.3   | 13.*    | ^11.0     | ^11.5.50   |

`fail-fast: false` keeps all combinations running; a failing combination still fails the
workflow. Combine the workflow with required status checks on `main` so a failing matrix
blocks merges even when the local hook is bypassed (`--no-verify`).

### What should be tested

Payment:

* successful and failed payment flows
* invalid payment data (`amount`, `customer.id`, `customer.email`)
* customer creation/reuse and transaction persistence
* gateway response mapping and status mapping
* retry (valid states, invalid states)
* refunds (full, partial, over-refund, reference mismatch, remaining amount)

Callbacks and webhooks:

* valid / invalid / missing signature (Stripe), valid / invalid HMAC (Paymob)
* unknown transaction, already-processed transaction
* handled and unhandled event types
* the Stripe refund race (`refund.created` before/after `charge.refunded`)

Subscriptions (**not covered yet** — recommended additions):

* `createPlan()` payload mapping + duplicate-name failure
* plan update payload (only `amount` and `number_of_deductions` are sent)
* plan suspend/resume refreshing the local plan
* `subscribe()`: intention request shape, local `pending` transaction, returned keys
* initial subscription callback: subscription lookup, `initial_transaction` validation,
  local subscription creation, transaction linking, webhook registration
* lifecycle webhook: trigger-type routing, idempotency via
  `subscription_webhook_events`, `UpdateSubscriptionJob` success and failure paths,
  unknown trigger types being ignored
* waiting for a queue worker (`Queue::fake()` / `Bus::fake()` assertions)

---

## Extending gateways

Adding a gateway should not require touching the shared payment workflow.

```text
src/
└── Gateways/
    └── Tap/
        ├── TapGateway.php
        ├── Handlers/                  # optional: callback handlers
        │   └── TapCallbackHandler.php
        └── Services/
            ├── TapApiService.php
            └── TapWebhookHandler.php
```

### 1. Create the gateway

```php
class TapGateway extends BaseGateway implements PaymentGatewayInterface
{
    public function __construct(
        CustomerSerivce $customerService,
        TransactionRepositoryInterface $transactionRepository,
        private TapApiService $apiService,
    ) {
        parent::__construct($customerService, $transactionRepository);

        $this->gateway_name = 'tap';
    }

    // ...
}
```

### 2. Implement the required gateway hooks

```php
protected function sendPaymentRequest(PaymentRequestDTO $paymentDto): array;

protected function buildPaymentTransactionDTO(array $apiResponse, PaymentRequestDTO $paymentDto): PaymentTransactionDTO;
```

Override `ensureGatewayCustomer()` when the provider keeps its own customer objects
(Stripe does this).

### 3. Implement the gateway contract

`pay()`, `verify()`, `getTransactions()`, `getCustomerTransactions()`,
`getGatewayTransactionByOrderId()`, `retryPayment()`, and `refund()`.

### 4. Register the driver

Add the class to `config/ma_payment_drivers.php` (config key `ma-drivers`):

```php
return [
    'stripe' => \Ma\Payment\Gateways\Stripe\StripeGateway::class,
    'paymob' => \Ma\Payment\Gateways\Paymob\PaymobGateway::class,
    'tap'    => \Ma\Payment\Gateways\Tap\TapGateway::class,
];
```

The manager and factory need no changes.

### 5. Add configuration

Add provider keys to `config/ma_payment_conf.php` (config key `ma-payment`) using `env()`
for every value, and document them in [7. Configuration](07-configuration.md).

### 6. Implement callbacks

A gateway callback handler should verify the signature, parse the event, identify the
local transaction, map the status, and update the transaction safely (row locks /
database transactions where needed).

### 7. Add tests

Cover the payment happy path, failures, invalid input, verification, invalid signature,
duplicate callbacks, refunds (including over-refund), reference mismatch, and retry
where supported. Use `Http::fake()` for API calls, like the existing Paymob tests.

### 8. Update documentation

Add the gateway to the capability tables in
[1. Introduction](01-introduction.md) and the relevant sections of
[4. Payments](04-payments.md), plus any new configuration keys.

### Rules

**Do**

* extend `BaseGateway` and implement `PaymentGatewayInterface`
* keep API communication inside a gateway-specific API service
* keep callback parsing inside a gateway-specific callback handler
* reuse the existing repositories, DTOs, and status mapping
* store monetary values in minor units
* add automated tests

**Do not**

* duplicate `executePayment()`
* put gateway API calls inside repositories
* put gateway-specific logic in `BaseGateway`
* modify generic payment logic for a single gateway
* hard-code secrets
* pretend unsupported operations are supported

---

## Adding another subscription implementation

Subscriptions are a **capability**, not a driver. To support subscriptions for another
gateway:

### 1. Implement `SubscriptionInterface`

```php
namespace Ma\Payment\Gateways\YourGateway;

use Ma\Payment\Interfaces\SubscriptionInterface;

final class YourGatewaySubscription implements SubscriptionInterface
{
    public function createPlan(array $data): array { /* ... */ }

    public function findPlanByLocalId(int $id): SubscriptionPlan { /* ... */ }

    public function updateSubscriptionPlan(string $planId, array $data): array { /* ... */ }

    public function listPlans(): Collection { /* ... */ }

    public function suspendPlan(string $planId): array { /* ... */ }

    public function resumePlan(string $planId): array { /* ... */ }

    public function subscribe(array $plan, array $customerData): array { /* ... */ }

    public function paginateLocalSubscrptions(?int $perPage = 8): LengthAwarePaginator { /* ... */ }

    public function findSubscrptionByLocalId(int $id): Subscription { /* ... */ }

    public function suspendSubscription(string $subscriptionId): array { /* ... */ }

    public function resumeSubscription(string $subscriptionId): array { /* ... */ }

    public function lifeCycle(array $subscriptionData): void { /* ... */ }

    public function updateGatewaySubscription(string $subscriptionGatewayId, array $subscriptionData): array { /* ... */ }
}
```

Keep the method names exactly as declared — the interface (not only the Paymob
implementation) defines them, including `findSubscrptionByLocalId` and
`paginateLocalSubscrptions`.

### 2. Expose it from the gateway

```php
class YourGateway extends BaseGateway
    implements PaymentGatewayInterface, SubscrptionableInterface
{
    public function __construct(
        // ...
        private YourGatewaySubscription $subscription,
    ) {
        // ...
    }

    public function subscription(): SubscriptionInterface
    {
        return $this->subscription;
    }
}
```

### 3. Reuse the shared persistence

The existing schema and repositories are gateway-agnostic:

| Concern             | Reuse                                                                                         |
| ------------------- | ---------------------------------------------------------------------------------------------- |
| Local plans         | `subscription_plans` + `SubscriptionPlanRepository` (the `gateway` column distinguishes providers) |
| Local subscriptions | `subscriptions` + `SubscriptionRepository`                                                      |
| Initial transaction | `payment_transactions` (`subscription_id`, `subscription_transaction_type`) + `PaymentTransactionDTO` |
| Webhook idempotency | `subscription_webhook_events` + `SubscriptionWebhookEventRepository`                             |
| Statuses            | `SubscriptionStatus`, `SubscriptionTransactionType`                                             |

### 4. Follow the same lifecycle pattern

* create records locally when the gateway confirms them, not before;
* make webhook handling idempotent through `subscription_webhook_events`;
* do slow work in a queued job (like `UpdateSubscriptionJob`) rather than in the request;
* document which identifiers each method expects (local vs gateway) in
  [5. Subscriptions](05-subscriptions.md).

### 5. Update the documentation tables

Add the gateway to the subscription capability tables in
[1. Introduction](01-introduction.md), [5. Subscriptions](05-subscriptions.md), and
[6. Gateway Architecture](06-gateway-architecture.md).

---

## Project structure

```text
lara_payments_ma/
├── README.md                                    # entry point + navigation
├── docs/                                        # this documentation set
├── composer.json
├── phpunit.xml
├── .github/
│   ├── hooks/pre-push                            # runs composer test before pushing
│   └── workflows/test.yaml                       # PHP/Laravel matrix CI
├── config/
│   ├── ma_payment_conf.php                       # credentials (config key: ma-payment)
│   └── ma_payment_drivers.php                    # driver registry (config key: ma-drivers)
├── database/migrations/
│   ├── ..._create_payment_customers_table.php
│   ├── ..._create_subscription_plans_table.php
│   ├── ..._create_subscriptions_table.php
│   ├── ..._create_payment_transactions_table.php
│   ├── ..._create_refunded_payment_transactions_table.php
│   ├── ..._create_customer_cards_table.php
│   └── ..._create_subscription_webhook_events_table.php
├── resources/
│   ├── js/stripe/MaPaymentStripe.js
│   └── views/Stripe/card.blade.php
├── src/
│   ├── MaPaymentServiceProvider.php
│   ├── PaymentGatewayManager.php
│   ├── DTOS/
│   │   ├── PaymentRequestDTO.php
│   │   ├── PaymentTransactionDTO.php
│   │   ├── PaymobSubscriptionPlanRequestDTO.php
│   │   └── PaymobSubscriptionPlanResponseDTO.php
│   ├── Enums/
│   │   ├── PaymentStatus.php
│   │   ├── SubscriptionStatus.php
│   │   └── SubscriptionTransactionType.php
│   ├── Exceptions/                               # package exceptions
│   ├── Facades/MaPayment.php
│   ├── Factories/PaymentGatewayFactory.php
│   ├── Gateways/
│   │   ├── BaseGateway.php
│   │   ├── Paymob/
│   │   │   ├── PaymobGateway.php                 # implements SubscrptionableInterface
│   │   │   ├── PaymobSubscription.php            # implements SubscriptionInterface
│   │   │   ├── Handlers/
│   │   │   │   ├── PaymobTransactionCallbackHandler.php
│   │   │   │   ├── PaymobSubscriptionCallbackHandler.php
│   │   │   │   └── PaymobSubscriptionWebhookHandler.php
│   │   │   └── Services/
│   │   │       ├── PaymobApiService.php
│   │   │       └── PaymobSubscriptionService.php
│   │   └── Stripe/
│   │       ├── StripeGateway.php
│   │       └── Services/
│   │           ├── StripeApiService.php
│   │           └── StripeWebhookHandler.php
│   ├── Interfaces/
│   │   ├── PaymentGatewayInterface.php
│   │   ├── SubscriptionInterface.php
│   │   ├── SubscrptionableInterface.php
│   │   ├── TransactionRepositoryInterface.php
│   │   └── ViewableCheckoutGatewayInterface.php
│   ├── Jobs/
│   │   ├── UpdateRefundTransactionJob.php
│   │   └── UpdateSubscriptionJob.php
│   ├── Models/
│   │   ├── PaymentCustomer.php
│   │   ├── PaymentTransaction.php
│   │   ├── RefundedPaymentTransaction.php
│   │   ├── CustomerCard.php
│   │   ├── SubscriptionPlan.php
│   │   ├── Subscription.php
│   │   └── SubscriptionWebhookEvent.php
│   ├── Repositories/
│   │   ├── TransactionRepository.php
│   │   ├── PaymentCustomerRepository.php
│   │   ├── RefundTransactionRepository.php
│   │   ├── SubscriptionPlanRepository.php
│   │   ├── SubscriptionRepository.php
│   │   └── SubscriptionWebhookEventRepository.php
│   ├── Services/
│   │   ├── ClientApiService.php
│   │   └── CustomerSerivce.php                   # note the spelling in the filename
│   └── ValueObjects/
│       ├── Money.php
│       ├── UserEmail.php
│       └── UserId.php
└── tests/
    ├── TestCase.php
    ├── Feature/Paymob/SuccessfulPaymentTest.php
    ├── Feature/Paymob/FailedPaymentTest.php
    ├── Unit/Paymob/PaymentRequestDTOTest.php
    └── Unit/Paymob/PaymentTransactionDTOTest.php
```

---

## Contributing

When adding or changing functionality:

1. follow the existing architecture;
2. keep gateway-specific code inside its gateway directory;
3. avoid changing the shared payment workflow unnecessarily;
4. add or update tests and run the full suite (`composer test`);
5. update this documentation, including the capability tables;
6. preserve backward compatibility.

---

[← Previous: 8. Troubleshooting](08-troubleshooting.md) · [Back to the main README →](../README.md)







