# 5. Subscriptions

[← Documentation index](README.md)

Subscriptions are currently supported **for the Paymob gateway only**, introduced in
**v2.1.0** as a backward-compatible feature release.

You reach them through the gateway:

```php
use Ma\Payment\Facades\MaPayment;

$payment = MaPayment::driver('paymob');

$payment->subscription()->createPlan([...]);
$payment->subscription()->subscribe($plan, $customerData);
```

**Contents**

* [Subscription Overview](#subscription-overview)
* [Creating a Subscription Plan](#creating-a-subscription-plan)
* [Listing Subscription Plans](#listing-subscription-plans)
* [Updating a Subscription Plan](#updating-a-subscription-plan)
* [Suspending and Resuming a Subscription Plan](#suspending-and-resuming-a-subscription-plan)
* [Creating a Subscription](#creating-a-subscription)
* [Subscription Lifecycle](#subscription-lifecycle)
* [Updating a Subscription](#updating-a-subscription)
* [Subscription Callbacks / Webhooks](#subscription-callbacks--webhooks)
* [Subscription Transactions](#subscription-transactions)
* [Local data reference](#local-data-reference)
* [Current limitations](#current-limitations)

---

## Subscription Overview

You do not need to know Paymob's terminology to use this package. Here are the concepts
in plain terms.

### Subscription Plan

A **plan** is a reusable template that describes recurring billing: how much to charge
and how often. Example: *"Premium Monthly — 100 EGP, 12 deductions"*.

A plan exists twice:

* as a **local subscription plan** — a row in `subscription_plans`
* as a **gateway subscription plan** — a plan object at Paymob

The package creates both at the same time (`createPlan()`) and keeps them linked with
`gateway_plan_id`.

### Subscription

A **subscription** is a single customer's enrolment into a plan. If 200 customers
subscribe to the same plan, there is **1 plan** and **200 subscriptions**.

It also exists twice:

* as a **local subscription** — a row in `subscriptions`
* as a **gateway subscription** — a subscription at Paymob, linked with
  `gateway_subscription_id`

### Customer

The **customer** is the local package customer row in `payment_customers`, which maps
your application user to the gateway customer. A subscription belongs to exactly one
local customer (`subscriptions.customer_id`) and one local plan
(`subscriptions.plan_id`).

### Initial transaction

The **initial transaction** is the first payment a customer makes when subscribing. It
is what actually creates the subscription:

```text
Customer pays the first charge   →   Paymob creates the subscription
```

Locally, that payment is a `payment_transactions` row where:

* `subscription_id` points to the local subscription
* `subscription_transaction_type` = `initial`
* `source` = `card_subscription`

### Recurring billing

After the initial payment, **Paymob** performs the recurring charges according to the
plan. The package does not execute recurring charges and does not store recurring
transactions; it mirrors the subscription state Paymob reports
(see [Subscription Transactions](#subscription-transactions)).

### Subscription lifecycle

A subscription moves through states. The locally supported states come from
`Ma\Payment\Enums\SubscriptionStatus`:

```text
pending      # local default before the subscription exists / is confirmed
active       # set when the initial transaction callback creates the subscription
suspended    # Paymob reported a suspension
disabled     # Paymob reported the subscription as disabled
```

State changes reported by Paymob are applied to the local subscription by
[subscription lifecycle callbacks](#subscription-callbacks--webhooks).

### Local data vs gateway data

Wherever both exist, this documentation says which one is used.

| Concept               | Local (your database)                                        | Gateway (Paymob)                    |
| --------------------- | ------------------------------------------------------------ | ----------------------------------- |
| Subscription plan     | `subscription_plans` row — identified by local `id`, linked through `gateway_plan_id` | Paymob plan object |
| Subscription          | `subscriptions` row — identified by local `id`, linked through `gateway_subscription_id` | Paymob subscription |
| Initial transaction   | `payment_transactions` row with `subscription_id` and `subscription_transaction_type = initial` | Paymob transaction |
| Lifecycle events      | `subscription_webhook_events` row (idempotency log)          | Paymob webhook delivery             |
| Customer              | `payment_customers` row                                       | Paymob subscription owner           |

**Which ID does a method expect?**

| Method                                                        | Identifier                     |
| ------------------------------------------------------------- | ------------------------------ |
| `findPlanByLocalId($id)`                                       | **local** plan `id`            |
| `findSubscrptionByLocalId($id)`                                | **local** subscription `id`    |
| `updateSubscriptionPlan($planId, [...])`                       | **gateway** plan ID            |
| `suspendPlan($planId)` / `resumePlan($planId)`                 | **gateway** plan ID            |
| `suspendSubscription($subscriptionId)` / `resumeSubscription($subscriptionId)` | **gateway** subscription ID |
| `updateGatewaySubscription($subscriptionGatewayId, [...])`     | **gateway** subscription ID    |

The package store the local `id` if you need to look records up again, and read
`gateway_plan_id` / `gateway_subscription_id` when calling the gateway methods:

```php
$subscription = $payment->subscription()->findSubscrptionByLocalId($localId);

$payment->subscription()->suspendSubscription($subscription->gateway_subscription_id);
```

---

## Creating a Subscription Plan

```php
$response = $payment->subscription()->createPlan(array $data): array;
```

### Example

```php
use Ma\Payment\Facades\MaPayment;

$payment = MaPayment::driver('paymob');

$response = $payment->subscription()->createPlan([
    'name' => 'Premium Monthly',
    'frequency' => 1,
    'reminder_days' => 2,
    'retrial_days' => 3,
    'amount' => 100,                  // major units
    'planType' => 'rent',
    'use_transaction_amount' => false,
    'is_active' => true,
    'number_of_deductions' => 12,
]);

// $response is the raw Paymob response array.
```

### Fields

| Field                    | Type        | Required | Default | Notes                                                                 |
| ------------------------ | ----------- | :------: | ------- | --------------------------------------------------------------------- |
| `name`                   | string      |    ✅    | —       | Plan name. Must be **unique** locally.                                |
| `frequency`              | int         |    ✅    | —       | Billing frequency sent to Paymob; stored locally as `billing_interval_count`. |
| `reminder_days`          | int         |    ✅    | —       | Sent to Paymob as `reminder_days`.                                    |
| `retrial_days`           | int         |    ✅    | —       | Sent to Paymob as `retrial_days`.                                     |
| `amount`                 | float / int |    ✅    | —       | Amount in **major units**. Must be greater than `0`.                  |
| `number_of_deductions`   | int \| null |    ✅    | `null`  | Number of deductions/billing cycles; stored locally as `billing_cycles`. The key must be present, but `null` is accepted. |
| `planType`               | string      |    ❌    | `rent`  | Sent to Paymob as `plan_type`.                                        |
| `use_transaction_amount` | bool        |    ❌    | `false` | Sent to Paymob as `use_transaction_amount`.                           |
| `is_active`              | bool        |    ❌    | `true`  | Sent to Paymob as `is_active`; mirrored locally in `is_active`.        |

Notes:

* `frequency`, `reminder_days`, `retrial_days`, `planType`, and
  `use_transaction_amount` are forwarded to Paymob. Their exact billing semantics are
  defined by Paymob; the package stores and forwards them without altering them.
* The plan is created at Paymob against your **MOTO integration**
  (`PAYMOB_MOTO_INTEGRATION_ID`).
* `amount` is converted from major units to minor units using the package `Money` value
  object, so `100` is sent to Paymob as `amount_cents = 10000`.

### What gets stored locally

Creating the plan also writes a **local subscription plan**:

| Local column             | Source                                 |
| ------------------------ | -------------------------------------- |
| `gateway`                | `paymob`                                |
| `gateway_plan_id`        | Paymob plan ID from the response         |
| `name`                   | `name`                                   |
| `minor_amount`           | `amount_cents` from the response         |
| `billing_interval_count` | `frequency`                              |
| `billing_cycles`         | `number_of_deductions`                   |
| `is_active`              | `is_active` from the response            |
| `metadata`               | The raw Paymob response                  |

### Errors

Creating a plan whose `name` already exists locally throws:

```text
RuntimeException: Cannot create plan with name "<name>" because it is already exist
```

An `amount` of `0` or less throws
`InvalidArgumentException: Amount not valid it must be > 0`.

---

### Plan amount vs Subscription amount vs Initial transaction amount

These three amounts are related but not the same. Getting this wrong is the most common
source of confusion when working with subscriptions.

| Amount                             | Where it comes from                                                 | Unit                                            |
| ---------------------------------- | ------------------------------------------------------------------- | ----------------------------------------------- |
| **Plan amount**                    | `createPlan(['amount' => ...])` → Paymob `amount_cents`; stored locally in `subscription_plans.minor_amount` | You pass **major units**; stored/sent as **minor units** |
| **Subscription (initial checkout) amount** | Built by `subscribe()` from the plan's `minor_amount` and sent to Paymob with the subscription intention | **Minor units**, taken from the plan `minor_amount` |
| **Initial transaction amount**     | Read back from the Paymob intention response (`intention_detail.amount`) and stored in `payment_transactions.minor_amount` | **Minor units** |

In practice, for a plan created with `'amount' => 100`:

```text
Plan amount                     = 100     (major)  → 10000 minor at Paymob + locally
Initial checkout amount         = 10000   (minor, taken from the plan)
Initial transaction minor_amount= 10000   (what the customer actually paid)
```

Later, Paymob charges the customer according to the plan (or the subscription-level
amount if you change it — see [Updating a Subscription](#updating-a-subscription)).
The package does not calculate recurring amounts.

---

## Listing Subscription Plans

There are two different "plan lists". Only one is currently exposed by the package.

### Local subscription plans (what the public API returns)

```php
$plans = $payment->subscription()->listPlans();          // Collection<SubscriptionPlan>

$plan = $payment->subscription()->findPlanByLocalId($id); // one plan
```

* `listPlans()` returns a `Illuminate\Database\Eloquent\Collection` of
  **local** `Ma\Payment\Models\SubscriptionPlan` models.
* It reads the `subscription_plans` table and is **not** filtered by gateway, so if you
  ever store plans for more than one gateway, filter them yourself.
* `findPlanByLocalId()` returns a single local plan and throws
  `Illuminate\Database\Eloquent\ModelNotFoundException` when the ID does not exist.

```php
foreach ($payment->subscription()->listPlans() as $plan) {
    $plan->id;                  // local plan ID
    $plan->gateway_plan_id;     // gateway (Paymob) plan ID
    $plan->name;
    $plan->minor_amount;        // in minor units
    $plan->is_active;
}
```

### Gateway subscription plans

The plan **list at Paymob** is *not* exposed through the public API in this release.
`PaymobSubscriptionService` contains an internal `listGatewayPlans()` helper, but the
plan listing that the package publishes (`listPlans()`) reads local data only.

> Practical consequence: `listPlans()` tells you **what this package created/stored
> locally**. To see plans that exist only at Paymob, use the Paymob dashboard.

---

## Updating a Subscription Plan

```php
$response = $payment->subscription()->updateSubscriptionPlan(string $planId, array $data): array;
```

`$planId` is the **gateway (Paymob) plan ID**, i.e. the local `gateway_plan_id` value —
not the local `id`.

### Supported fields

Only two fields are accepted by the current implementation:

| Field                  | Type        | Notes                                                     |
| ---------------------- | ----------- | --------------------------------------------------------- |
| `amount`               | float / int | **Major units**; converted to `amount_cents` for Paymob.   |
| `number_of_deductions` | int         | Sent to Paymob and mirrored locally as `billing_cycles`.   |

```php
$plan = $payment->subscription()->findPlanByLocalId($localPlanId);

$payment->subscription()->updateSubscriptionPlan($plan->gateway_plan_id, [
    'amount' => 150,
    'number_of_deductions' => 24,
]);
```

After the gateway call, the local plan row is updated from the response, so
`minor_amount` and `billing_cycles` reflect the new values.

> **Not updateable through this method:** `name`, `frequency`, `reminder_days`,
> `retrial_days`, `planType`, `use_transaction_amount`, and `is_active`. They are not
> sent by `updateSubscriptionPlan()` and are not part of the update payload — use
> [suspend/resume](#suspending-and-resuming-a-subscription-plan) for the active flag,
> and Paymob's dashboard for anything else.

---

## Suspending and Resuming a Subscription Plan

```php
$payment->subscription()->suspendPlan(string $planId): array;
$payment->subscription()->resumePlan(string $planId): array;
```

`$planId` is the **gateway (Paymob) plan ID**.

```php
$plan = $payment->subscription()->findPlanByLocalId($localPlanId);

// Stop the plan from being used for new billing
$payment->subscription()->suspendPlan($plan->gateway_plan_id);

// Activate it again
$payment->subscription()->resumePlan($plan->gateway_plan_id);
```

What happens:

* `suspendPlan()` calls Paymob's plan `suspend` endpoint and refreshes the local plan
  row (`is_active`) from the response.
* `resumePlan()` calls Paymob's plan `resume` endpoint and refreshes the local plan row
  the same way.
* Both methods return the **raw Paymob response array**. Neither one deletes the plan,
  locally or at Paymob.

> **Suspending a plan is not the same as suspending a subscription.**
> Suspending a **plan** affects whether the plan can be used/billed;
> suspending a **subscription** affects one specific customer's subscription.
> They are separate methods (`suspendPlan()` vs `suspendSubscription()`) and separate
> Paymob endpoints. Existing subscriptions are not suspended by `suspendPlan()`.

Also note: unlike suspending/resuming a **subscription**, these plan methods refresh the
local plan row immediately from the Paymob response — no webhook is involved.

---

## Creating a Subscription

```php
$result = $payment->subscription()->subscribe(array $plan, array $customerData): array;
```

This is the method that starts a subscription for one customer. It creates the Paymob
subscription **intention** (the initial checkout), stores a local `pending`
transaction, and returns the checkout URL. The subscription itself is created by Paymob
after the customer pays, and the package completes the local records when the callback
arrives.

### Step by step

```text
1. Create/select a plan
      createPlan(...)  →  local plan + Paymob plan
                     or findPlanByLocalId($id)

2. Create the subscription intention
      subscribe($plan, $customerData)
      → Paymob intention (subscription_plan_id, amount, customer billing data)
      → local payment_transactions row (status: pending, source: card_subscription)
      → returns ['payLink' => ..., 'transacrion' => ...]

3. Customer completes the initial payment in Paymob's unified checkout

4. Paymob creates the subscription

5. Paymob sends the callback to your payment callback route
      $gateway->verify($request->all())

6. The package persists the local subscription and links the initial transaction
      subscriptions row (status: active)
      payment_transactions.subscription_id + subscription_transaction_type = initial
      subscription webhook registered at Paymob
```

### Arguments

**`$plan`** must expose the local plan's `gateway_plan_id`, `minor_amount`, and `name`.
Passing the model returned by `findPlanByLocalId()` works:

```php
$plan = $payment->subscription()->findPlanByLocalId($localPlanId);
```

**`$customerData`** is used for two things: the local customer row and the Paymob
billing data.

| Key       | Required | Notes                                                                                  |
| --------- | :------: | -------------------------------------------------------------------------------------- |
| `user_id` | ✅ for a new customer | Your application user ID. Used to find or create the local `payment_customers` row. If omitted and no customer exists, creation relies on your database defaults for `user_id` (which is `NOT NULL` and unique), so always pass it. |
| `name`    | ✅       | Split on spaces: the first part is used as the billing first name and the last part as the billing last name. |
| `email`   | ✅       | Sent to Paymob as billing email. **Also the key used later to attach the subscription to the local customer** (the initial callback looks the customer up by email). |
| `phone`   | ✅       | Sent to Paymob as billing phone number.                                                 |

The `gateway` value `paymob` is added by the package; you do not pass it.

### Example

```php
use Ma\Payment\Facades\MaPayment;

$payment = MaPayment::driver('paymob');

$plan = $payment->subscription()->findPlanByLocalId($localPlanId);

$result = $payment->subscription()->subscribe($plan, [
    'user_id' => auth()->id(),
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '01010101010',
]);

$intentionResponse = $result['transacrion'];   // raw Paymob intention response
$checkoutUrl = $result['payLink'];             // send the customer here

return redirect($checkoutUrl);
```

### Return value

```php
[
    'payLink'    => 'https://accept.paymob.com/unifiedcheckout/?publicKey=...&clientSecret=...',
    'transacrion' => [ /* raw Paymob intention response */ ],
]
```

> The second key is spelled `transacrion` in the current implementation. Use that exact
> spelling when reading the raw intention response.

The intention response is read by the package for:

* `intention_order_id` → stored as the local transaction `order_id`
* `intention_detail.amount` → stored as the local transaction `minor_amount`
* `intention_detail.currency` → stored as the local transaction `currency`

### What is stored right away

A local transaction is created with status `pending`:

| Column               | Value                                          |
| -------------------- | ---------------------------------------------- |
| `gateway`            | `paymob`                                        |
| `order_id`           | `intention_order_id` from the intention         |
| `customer_id`        | The local customer ID                           |
| `minor_amount`       | `intention_detail.amount`                       |
| `currency`           | `intention_detail.currency`                     |
| `source`             | `card_subscription`                             |
| `status`             | `pending`                                       |
| `meta_data`          | The raw intention transaction payload           |
| `subscription_id`    | `null` until the callback is processed          |

The local **subscription** row is *not* created here — that happens in step 6.

---

## Subscription Lifecycle

Two lifecycles exist and are easy to confuse:

| | Plan lifecycle                     | Subscription lifecycle                 |
| --- | -------------------------------- | -------------------------------------- |
| Object | The reusable template          | One customer's enrolment                |
| Identified by | `gateway_plan_id`        | `gateway_subscription_id`               |
| Local table | `subscription_plans`        | `subscriptions`                          |
| Operations | `suspendPlan`, `resumePlan`, `updateSubscriptionPlan` | `suspendSubscription`, `resumeSubscription`, `updateGatewaySubscription` |
| Affects | Which plans can be used/billed | One customer's subscription             |

### Listing and finding local subscriptions

```php
$subscriptions = $payment->subscription()->paginateLocalSubscrptions(15); // LengthAwarePaginator

$subscription = $payment->subscription()->findSubscrptionByLocalId($localId); // Subscription
```

* `paginateLocalSubscrptions(?int $perPage = 8)` paginates **local** subscriptions for
  the `paymob` gateway.
* `findSubscrptionByLocalId(int $id)` returns one local subscription and throws
  `Illuminate\Database\Eloquent\ModelNotFoundException` when the ID does not exist.

```php
foreach ($payment->subscription()->paginateLocalSubscrptions(15) as $subscription) {
    $subscription->id;                        // local ID
    $subscription->gateway_subscription_id;   // gateway ID
    $subscription->status;                    // active | suspended | disabled | pending
    $subscription->next_billing;              // date of the next scheduled billing
    $subscription->starts_at;
    $subscription->ends_at;
    $subscription->customer;                  // PaymentCustomer
    $subscription->subscriptionPlan;          // SubscriptionPlan
    $subscription->paymentTransactions;       // initial + any linked transactions
}
```

### Suspending a subscription

```php
$payment->subscription()->suspendSubscription(
    $subscription->gateway_subscription_id
);
```

* Calls Paymob's subscription `suspend` endpoint.
* Returns the **raw Paymob response array**.
* **Does not change the local `subscriptions.status`.** The local status is updated when
  Paymob sends the `suspended` lifecycle webhook and your application forwards it to
  `lifeCycle()`.

### Resuming a subscription

```php
$payment->subscription()->resumeSubscription(
    $subscription->gateway_subscription_id
);
```

* Same behaviour: Paymob `resume` endpoint, raw response, no direct local status update.

> **Why the webhook matters:** if you call `suspendSubscription()` and then immediately
> read the local subscription, the status will usually still be `active`. Run a queue
> worker and make sure the lifecycle webhook route is configured, then read the status
> after the `suspended` event has been processed.

### Local subscription fields

| Column                    | Meaning                                                            |
| ------------------------- | ------------------------------------------------------------------ |
| `gateway`                 | `paymob`                                                            |
| `gateway_subscription_id` | Paymob subscription ID                                              |
| `customer_id`             | Local customer ID                                                   |
| `plan_id`                 | Local plan ID                                                       |
| `next_billing`            | Date of the next scheduled billing                                  |
| `starts_at`               | Subscription start date                                             |
| `ends_at`                 | Subscription end date (when Paymob reports one)                     |
| `reminder_date`           | Reminder date reported by Paymob                                    |
| `suspended_at`            | When the subscription was suspended                                 |
| `resumed_at`              | When the subscription was resumed                                   |
| `reactivated_at`          | When the subscription was reactivated                               |
| `status`                  | `pending` (default) · `active` · `suspended` · `disabled`            |

---

## Updating a Subscription

```php
$response = $payment->subscription()->updateGatewaySubscription(
    string $subscriptionGatewayId,
    array $subscriptionData
): array;
```

`$subscriptionGatewayId` is the **gateway (Paymob) subscription ID**
(`subscriptions.gateway_subscription_id`).

```php
$subscription = $payment->subscription()->findSubscrptionByLocalId($localId);

$payment->subscription()->updateGatewaySubscription(
    $subscription->gateway_subscription_id,
    [
        'amount' => 15000,                 // see the notes below
        'next_billing' => '2026-10-01',
        'ends_at' => '2027-10-01',
    ]
);
```

### Supported fields

The package **forwards the array as-is** to Paymob's
`PUT /acceptance/subscriptions/{id}` endpoint (only the authentication token is added).
It does not rename, validate, or transform the keys, and it does not update the local
subscription row. The three fields used in practice are:

| Field          | Meaning                                                                                      |
| -------------- | -------------------------------------------------------------------------------------------- |
| `amount`       | The amount Paymob will use for the subscription's next charge. The package does **not** convert it, so pass the value in the unit Paymob expects for this endpoint. |
| `next_billing` | The **date of the next scheduled billing**. It changes *when* the next charge happens — it does not trigger an immediate charge. |
| `ends_at`      | The date the subscription ends.                                                                |

> ⚠️ **`next_billing` is not "charge now".** Setting `next_billing` reschedules the next
> billing date; it does not create a payment. If you need an immediate charge, that is
> not something this package offers.

### What is and is not stored locally

* `updateGatewaySubscription()` returns the raw Paymob response and **does not** write to
  the local `subscriptions` row.
* Local `amount` is not a column of `subscriptions`; the local table stores the plan
  reference (`plan_id`) and the dates. Local dates (`next_billing`, `starts_at`,
  `ends_at`, `reminder_date`, `suspended_at`, `resumed_at`, `reactivated_at`) and
  `status` are refreshed from Paymob's **lifecycle webhooks**
  (`updated`, `suspended`, `resumed`, `cancelled`).
* Because the payload is forwarded transparently, only send keys you know Paymob accepts.
  Unsupported keys will be rejected by Paymob or ignored by it — the package will not
  catch that for you.

### Cancellation

There is no dedicated cancel method in the package. Paymob's lifecycle webhooks include
a `cancelled` trigger type, which the lifecycle handler processes like the other
state changes (see below).

---

## Subscription Callbacks / Webhooks

Subscriptions use **two different callback flows**. They arrive at two different
application routes and they do different things.

| Flow                          | Where it goes                                    | Purpose                                              |
| ----------------------------- | ------------------------------------------------ | ---------------------------------------------------- |
| Subscription **transaction** callback | your payment callback route → `$gateway->verify($request->all())` | Confirm the initial payment and create the local subscription |
| Subscription **lifecycle** webhook | your subscription webhook route → `$payment->subscription()->lifeCycle($request->all())` | Apply `suspended` / `resumed` / `cancelled` / `updated` state changes |

Both routes are yours to create (see [2. Installation](02-installation.md)).

### How the package tells them apart

A subscription transaction callback is identified by the presence of an `intention` key
in the Paymob payload:

```php
// inside PaymobGateway::verify()
$webhook = isset($processedTransaction['intention'])
    ? $this->paymobSubscriptionCallbackHandler->handle($processedTransaction)
    : $this->paymobCallbackHandler->handle($processedTransaction);
```

* `intention` present → subscription transaction callback handler
* `intention` absent → normal payment callback handler
  (see [4. Payments → Payment Callbacks / Webhooks](04-payments.md))

Lifecycle webhooks never reach `verify()`. They are identified by the endpoint you send
them to, and are recognised by their `trigger_type`:

```php
match ($triggerType) {
    'resumed'   => ...,
    'suspended' => ...,
    'cancelled' => ...,
    'updated'   => ...,
    default     => /* logged and ignored */,
};
```

**Supported trigger types:** `resumed`, `suspended`, `cancelled`, `updated`.
Any other trigger type is only written to the log
(`extra triger types are not defined`) and is otherwise ignored.

### Initial transaction callback → `verify()`

This is the callback that creates the subscription. When it is handled, the package:

1. computes the callback HMAC from the transaction payload;
2. **verifies the subscription against Paymob** instead of trusting the callback:
   it calls Paymob's `subscriptions?transaction=<transactionId>` lookup and retries with
   increasing delays (`2, 4, 6, 8` seconds, so up to ~20 seconds of blocking);
3. throws `RuntimeException('Subscription not found at Paymob')` if Paymob does not
   report a subscription;
4. throws `RuntimeException('Subscription does not belong to the callback transaction.')`
   when Paymob's `initial_transaction` does not match the callback transaction;
5. finds the local transaction by gateway order ID and updates it:
   `gateway_reference`, `status` (`succeeded` or `failed`), `source_subtype`;
6. creates the **local subscription** with:
   * `gateway` — the transaction's gateway (`paymob`)
   * `gateway_subscription_id` — Paymob subscription ID
   * `customer_id` — the transaction's local customer
   * `plan_id` — the **local** plan found by the subscription's `plan_id`
     (the local plan must exist, since it is looked up by `gateway_plan_id`)
   * `next_billing`, `starts_at`, `ends_at`, `reminder_date` — from Paymob
   * `status` — `active`
7. registers the subscription webhook at Paymob
   (`register_webhook` with `PAYMOB_SUBSCRIPTION_WEBHOOK_URL`);
8. links the transaction to the subscription:
   `subscription_id` = the new local subscription ID and
   `subscription_transaction_type` = `initial`.

```text
Paymob callback (has `intention`)
      │
      ▼
verify()  →  subscription callback handler
      │
      ▼
look up subscription at Paymob (retry 2/4/6/8s)
      │
      ▼
validate initial_transaction matches
      │
      ▼
DB transaction
      ├── update local transaction
      ├── create local subscription (status: active)
      ├── register webhook at Paymob
      └── link transaction → subscription
```

#### Notes and requirements

* The local customer is resolved **by email** (`client_info.email` in Paymob's
  subscription payload), which is why the email you pass to `subscribe()` matters.
* The local `pending` transaction created by `subscribe()` must still exist; otherwise
  `Ma\Payment\Exceptions\TransactionNotFoundException` is thrown.
* If the transaction is no longer `pending`,
  `Ma\Payment\Exceptions\TransactionAlreadyProccessedException` is thrown.
* `PAYMOB_SUBSCRIPTION_WEBHOOK_URL` must be configured, or Paymob has no webhook URL to
  register for the new subscription.
* The lookup retries sleep inside the HTTP request, so this endpoint can take up to
  about 20 seconds. Make sure your web server / PHP timeouts allow for that.
* **Verification note:** the HMAC comparison inside the subscription callback handler is
  currently commented out in the implementation; the package relies on the Paymob
  subscription lookup described above to validate the callback instead. This is
  different from normal payment callbacks, where an invalid HMAC throws.

### Subscription lifecycle webhook → `lifeCycle()`

```php
Route::post('/paymob/subscription/webhook', function (Illuminate\Http\Request $request) {
    MaPayment::driver('paymob')->subscription()->lifeCycle($request->all());

    return response()->json(['received' => true]);
});
```

Payload keys read by the package:

| Key                 | Used for                                                       |
| ------------------- | -------------------------------------------------------------- |
| `trigger_type`      | Which lifecycle change happened (`resumed`, `suspended`, `cancelled`, `updated`) |
| `paymob_request_id` | Idempotency key — stored as the webhook event ID                |
| `subscription_data` | The new subscription state (see below)                          |

`subscription_data` keys applied to the local subscription:

| Key               | Local column       |
| ----------------- | ------------------ |
| `id`              | Used to find the local subscription by `gateway_subscription_id` |
| `state`           | `status`            |
| `next_billing`    | `next_billing`      |
| `starts_at`       | `starts_at`         |
| `ends_at`         | `ends_at`           |
| `reminder_date`   | `reminder_date`     |
| `suspended_at`    | `suspended_at`      |
| `resumed_at`      | `resumed_at`        |
| `reactivated_at`  | `reactivated_at`    |

Processing flow:

```text
POST → your route → lifeCycle($payload)
      │
      ▼
store/load a row in subscription_webhook_events
      │
      ├── already processed?  →  stop (idempotent)
      │
      ▼
dispatch UpdateSubscriptionJob  →  queue worker
      │
      ▼
update the local subscription + mark the event processed
```

* **Idempotency:** events are recorded in `subscription_webhook_events` with a unique
  `(gateway, event_id)` pair. A repeated delivery does not dispatch the job again.
* **Queue required:** `UpdateSubscriptionJob` implements `ShouldQueue`. Without a queue
  worker the local subscription is never updated.
* **Retries:** the job has `$tries = 3`. When it finally fails, the event row stores the
  error message in `failure_reason` (and keeps `processed_at` empty), so you can inspect
  and replay it.
* **Local subscription must already exist:** the job looks the subscription up by
  `gateway_subscription_id`, so lifecycle webhooks only work after the initial
  transaction callback has created the local subscription.
* **Status values:** `subscription_data.state` is written straight into the local
  `status` column, whose allowed values are `active`, `disabled`, `suspended`, and
  `pending`.
* **Security note:** the package does not verify a signature or HMAC for lifecycle
  webhooks — whatever is POSTed to your route is processed. Protect the endpoint in your
  application (a secret URL segment, an IP allow-list, or your own validation) if you
  need stronger guarantees.

### Handled callback types at a glance

| Callback                                    | Entry point                        | Handled |
| ------------------------------------------- | ---------------------------------- | ------- |
| Subscription initial transaction (`intention` present) | `$gateway->verify()`     | ✅      |
| Subscription lifecycle `resumed`            | `subscription()->lifeCycle()`       | ✅      |
| Subscription lifecycle `suspended`          | `subscription()->lifeCycle()`       | ✅      |
| Subscription lifecycle `cancelled`          | `subscription()->lifeCycle()`       | ✅      |
| Subscription lifecycle `updated`            | `subscription()->lifeCycle()`       | ✅      |
| Any other `trigger_type`                    | `subscription()->lifeCycle()`       | ❌ logged only |

---

## Subscription Transactions

### Initial subscription transaction

The first charge of a subscription is created as a special local transaction:

```text
subscribe()
   ↓  creates
payment_transactions  (status: pending, source: card_subscription)
   ↓  the initial callback updates it
payment_transactions  (status: succeeded | failed)
   + subscription_id, subscription_transaction_type = initial
```

Reading it:

```php
$subscription = $payment->subscription()->findSubscrptionByLocalId($localId);

$initial = $subscription->paymentTransactions
    ->where('subscription_transaction_type', 'initial')
    ->first();
```

You can also query the transaction model directly:

```php
$initial = Ma\Payment\Models\PaymentTransaction::query()
    ->where('subscription_id', $localId)
    ->where('subscription_transaction_type', 'initial')
    ->first();
```

### Recurring (renewal) transactions

Recurring charges are executed by **Paymob**. The package does **not** persist them:

* `Ma\Payment\Enums\SubscriptionTransactionType` defines `initial` and `renewal`, and
  `payment_transactions.subscription_transaction_type` accepts both values,
* but the current implementation only ever writes `initial`.

So `subscription_transaction_type = renewal` should be treated as **reserved for future
use**. If you need a record of recurring charges, retrieve them from Paymob (the package
does not expose a public method for Paymob's per-subscription transaction list), or
store them in your own tables from your own reconciliation job.

### Failed transaction and retry

* If the initial payment fails, the initial callback sets the transaction status to
  `failed` and **no** local subscription is created for that attempt.
* `retryPayment()` can be used again on that local transaction. The retry check allows a
  transaction whose `source` is `card_subscription` even when its status is not
  `failed`/`pending`:

```php
$paylink = $payment->retryPayment($localTransactionId);
```

* A retry reuses the **same** local transaction row (it is updated, not duplicated) and
  returns a new payment link.

### What is not implemented

* No subscription-specific transaction listing API.
* No invoice / charge-history API.
* No automatic local record for a recurring charge.
* No subscription-specific refund API (see
  [4. Payments → Refunds](04-payments.md)).

---

## Local data reference

### `subscription_plans`

| Column                    | Notes                                              |
| ------------------------- | -------------------------------------------------- |
| `id`                      | Local plan ID                                       |
| `gateway`                 | `paymob`                                            |
| `gateway_plan_id`         | Paymob plan ID (unique)                             |
| `name`                    | Plan name (unique)                                  |
| `minor_amount`            | Plan amount in minor units                          |
| `billing_interval_count`  | From `frequency`                                    |
| `billing_cycles`          | From `number_of_deductions`                         |
| `is_active`               | Defaults to `true`                                  |
| `metadata`                | Raw Paymob plan response                            |

Relation: `SubscriptionPlan::subscriptions()` → `subscriptions.plan_id`.

### `subscriptions`

See [Local subscription fields](#local-subscription-fields).

Relations:

* `Subscription::customer()` → `PaymentCustomer` (`customer_id`)
* `Subscription::subscriptionPlan()` → `SubscriptionPlan` (`plan_id`)
* `Subscription::paymentTransactions()` → `PaymentTransaction` (`subscription_id`)
* `PaymentCustomer::subscriptions()` → `Subscription` (`customer_id`)

### `subscription_webhook_events`

| Column            | Notes                                        |
| ----------------- | -------------------------------------------- |
| `gateway`         | `paymob`                                      |
| `event_id`        | Paymob request ID (`paymob_request_id`)        |
| `event_type`      | `trigger_type`                                 |
| `payload`         | Full webhook payload                           |
| `processed_at`    | Set when the job succeeds                      |
| `failure_reason`  | Error message when the job finally fails       |

Unique constraint: `(gateway, event_id)`.

### Relationships overview

```text
payment_customers
   │
   ├── payment_transactions  ── subscription_id ──┐
   │       │                                       │
   │       └── refunded_payment_transactions       │
   │                                               ▼
   ├── customer_cards                          subscriptions
   │                                               │
   └── subscriptions ──────────────────────────────┤
                                                   │
                                        plan_id ───┘
                                            │
                                            ▼
                                    subscription_plans
```

---

## Current limitations

Documented explicitly so you can plan around them:

| Limitation                                                        | Detail                                                                                          |
| ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Paymob only                                                       | Stripe does not implement the subscription contract, so `MaPayment::driver('stripe')->subscription()` is not available. |
| No public gateway plan listing                                    | `listPlans()` returns **local** plans; Paymob's plan list is not exposed.                        |
| No recurring transaction records                                  | Only the initial transaction is stored locally; `renewal` exists in the enum but is never written. |
| Suspend/resume subscription do not update local status directly    | The local status changes only when the lifecycle webhook is processed by a queue worker.         |
| Lifecycle webhooks are not signature-verified                     | Protect the endpoint in your application.                                                        |
| No cancel method                                                  | `cancelled` is only handled as an incoming lifecycle trigger.                                     |
| Plan update is limited                                            | Only `amount` and `number_of_deductions` are supported by `updateSubscriptionPlan()`.             |
| Subscription update is a passthrough                              | `updateGatewaySubscription()` forwards your array unchanged and does not update local state.       |
| The initial transaction is not stored on the subscription row      | The link is stored on the **transaction** (`subscription_id`).                                   |
| Blocking retry loop in the initial callback                        | Up to ~20 seconds of `sleep()` while Paymob's subscription record becomes available.              |
| `createPlan()` requires a unique plan name                        | A duplicate name throws `RuntimeException`.                                                       |

---

## Subscription troubleshooting quick reference

| Symptom                                                              | Likely cause / fix |
| -------------------------------------------------------------------- | ------------------ |
| `RuntimeException: Subscription not found at Paymob`                   | Paymob had not reported the subscription yet, or the callback did not belong to a subscription. |
| `RuntimeException: Subscription does not belong to the callback transaction.` | Paymob's `initial_transaction` did not match the callback transaction. |
| Local subscription status stays `active` after `suspendSubscription()` | The `suspended` lifecycle webhook has not been forwarded to `lifeCycle()` yet, or no queue worker is running. |
| Local subscription never becomes `active`                              | The initial callback was not received, or the local pending transaction did not exist. |
| `TransactionAlreadyProccessedException`                                | The same callback was delivered twice. |
| Subscription webhook rows with `failure_reason`                        | The queued `UpdateSubscriptionJob` failed (often because the local subscription did not exist). |

More general error handling is covered in [8. Troubleshooting](08-troubleshooting.md).

---

[← Previous: 4. Payments](04-payments.md) · [Next: 6. Gateway Architecture →](06-gateway-architecture.md)











