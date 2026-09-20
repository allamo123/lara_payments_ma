# 8. Troubleshooting

[← Documentation index](README.md)

Every error below exists in the current implementation. If you have no exception,
start with the "No error, but nothing happens" section.

---

## Troubleshooting checklist

1. ✅ `php artisan migrate` has been run (including the `v2.1.0` subscription migrations).
2. ✅ The relevant `.env` variables are set — see [7. Configuration](07-configuration.md).
3. ✅ `php artisan config:clear` was run after changing `.env`.
4. ✅ Your callback routes exist, are POST routes, and are excluded from CSRF.
5. ✅ A queue worker is running (`php artisan queue:work`) for refund/subscription jobs.
6. ✅ `PAYMOB_SUBSCRIPTION_WEBHOOK_URL` points to a publicly reachable URL (subscriptions).

---

## No error, but nothing happens

| Symptom                                            | Likely cause                                                                                            |
| -------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Local transaction stays `pending` forever          | The gateway callback never reached your route, or the route is not wired to `verify()`.                  |
| Local subscription stays `pending` (or is missing) | The initial subscription callback was never received/processed, so the local subscription row was never created. |
| Local subscription status does not change after `suspendSubscription()` | The lifecycle webhook is not forwarded to `lifeCycle()`, or no queue worker is running. |
| `subscription_webhook_events` rows with `processed_at = null` | The queue worker is not running, so `UpdateSubscriptionJob` never executes.            |
| Refund status never updates (Stripe)               | `UpdateRefundTransactionJob` is queued but no worker is running.                                         |

---

## Package exceptions

All package exceptions live in `Ma\Payment\Exceptions` unless noted.

| Exception / error                                        | Thrown when                                                                                       | What to check |
| -------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | ------------- |
| `MissingPaymentInfoException`                            | *(defined by the package but not thrown by the current implementation)*                             | — |
| `CustomerNotFoundException`                              | `getCustomerTransactions()` / customer update is called for a user with no `payment_customers` row    | Make a payment first, or verify the `customer.id` you pass to `pay()` |
| `TransactionNotFoundException`                           | A callback or refund references a local transaction that does not exist                               | The `order_id` / `gateway_reference` mapping, and whether `pay()` succeeded |
| `TransactionAlreadyProccessedException`                  | A Paymob callback arrives for a transaction that is no longer `pending`                                | Duplicate callback deliveries — this is expected for replays |
| `TransactionCannotProcessException`                      | Paymob `retryPayment()` is called for a transaction that is not `failed`/`pending` and is not a subscription transaction | The transaction `status` and `source` |
| `TransactionFailedException`                             | *(defined by the package but not thrown by the current implementation)*                               | — |
| `GatewayTxnIdAndLocalTxnIdNotSameException`              | The gateway refund response transaction ID does not match the local `gateway_reference`               | Whether the refund belongs to a different transaction |
| `GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException`    | The gateway refund response order ID does not match the local `order_id` (note the typo in the class name) | Same as above |
| `RefundAmountGreaterThanTransactionAmountException`      | The refund amount exceeds `remain_minor_amount`                                                        | The transaction's `remain_minor_amount` (minor units) |
| `RefundTransactionNotFoundException`                     | `UpdateRefundTransactionJob` cannot find the refund record yet — thrown so Laravel retries the job      | Whether `refund.created` arrived before `charge.refunded` |

### Other exceptions thrown by the package

| Error                                                                | Source                        | Cause |
| -------------------------------------------------------------------- | ----------------------------- | ----- |
| `InvalidArgumentException: Amount not valid it must be > 0`           | `Money` value object           | `amount` is `0` or negative in `pay()`, a plan `amount`, or a refund |
| `InvalidArgumentException: Invalid email`                             | `UserEmail` value object       | The customer email is not a valid email address |
| `InvalidArgumentException: User ID cannot less than zero`             | `UserId` value object          | `customer.id` is missing or `<= 0` |
| `InvalidArgumentException: Not valid gatway name`                     | `PaymentTransactionDTO`        | An empty `gateway` value reached transaction persistence |
| `InvalidArgumentException: Stripe webhook payload must be a string.`  | `StripeGateway::verify()`      | You passed `$request->all()` instead of `$request->getContent()` |
| `InvalidArgumentException: Stripe webhook signature is required.`     | `StripeGateway::verify()`      | The `Stripe-Signature` header is missing |
| `Exception: Payment gateway [<driver>] does not exist`                | `PaymentGatewayFactory`        | The driver is not in `config('ma-drivers')` (or is misspelled) |
| `Exception: [<class>] must implement PaymentGatewayInterface.`        | `PaymentGatewayFactory`        | A registered gateway class does not implement the contract |
| `Illuminate\Database\Eloquent\ModelNotFoundException`                 | `findPlanByLocalId()`, `findSubscrptionByLocalId()`, subscription lookups by gateway ID | The local ID does not exist |
| `Illuminate\Http\Client\RequestException`                             | Paymob secret-key requests (`postWithSecretKey`) | Paymob rejected the request (credentials, plan, or intention payload) |
| `RuntimeException: Invalid Paymob transaction HMAC.`                  | `PaymobTransactionCallbackHandler` | `PAYMOB_HMAC` is wrong, or the callback payload was modified |
| `RuntimeException: type not exist at webhook handler`                 | Paymob callback handler        | The Paymob callback `type` is neither `TRANSACTION` nor `TOKEN` |
| `RuntimeException: Transaction does not exist at subscription webhook handler.` | Subscription callback handler | A subscription callback without a `transaction` key |
| `RuntimeException: HMAC does not exist at subscription webhook handler.` | Subscription callback handler | A subscription callback without an `hmac` key |
| `RuntimeException: Subscription not found at Paymob`                  | `PaymobGateway::verify()`      | The Paymob subscription lookup returned nothing after the retries |
| `RuntimeException: Subscription does not belong to the callback transaction.` | `PaymobGateway::verify()` | Paymob's `initial_transaction` did not match the callback transaction |
| `RuntimeException: Cannot create plan with name "<name>" because it is already exist` | `PaymobSubscriptionService::createPlan()` | A local plan with the same `name` already exists |
| `Stripe\Exception\CardException`                                      | Stripe SDK                     | The card was declined — the failed attempt is still stored locally |

---

## Common problems

### "My Paymob callback returns an error"

* Confirm the route calls `verify($request->all())` and that the payload is not
  transformed before it reaches the gateway.
* Verify `PAYMOB_HMAC` matches the HMAC secret configured in the Paymob dashboard.
* A transaction that is already processed throws
  `TransactionAlreadyProccessedException` — check whether the same callback was delivered
  twice.

### "My Stripe webhook throws an InvalidArgumentException"

`StripeGateway::verify()` requires the **raw request body** and the signature header:

```php
$gateway->verify(
    $request->getContent(),                 // raw string, not an array
    $request->header('Stripe-Signature')
);
```

Unsupported events are returned as `['handled' => false, 'event_type' => ...]`, and an
unknown transaction throws `TransactionNotFoundException`.

### "Subscription is stuck in pending"

The local subscription is only created by the **initial transaction callback**:

* make sure the callback route uses `verify()` and the payload contains `intention`
  (that is how the package recognises a subscription callback);
* make sure the local `payment_transactions` row created by `subscribe()` still exists
  (deleting it causes `TransactionNotFoundException`);
* make sure the plan exists **locally** — the callback looks the local plan up by
  `gateway_plan_id`, so a plan created outside the package cannot be attached.

### "Suspending a subscription does not change its status"

`suspendSubscription()` only calls Paymob. The local status changes when the `suspended`
lifecycle webhook is delivered **and** `UpdateSubscriptionJob` runs. Check:

1. `PAYMOB_SUBSCRIPTION_WEBHOOK_URL` is correct and reachable;
2. your lifecycle route calls `lifeCycle($request->all())`;
3. a queue worker is running;
4. the `subscription_webhook_events` row for the event has no `failure_reason`.

### "Subscription plan creation fails because the name exists"

Local plan names are unique (`subscription_plans.name`). Use a different name, or work
with the existing plan:

```php
$plan = MaPayment::driver('paymob')->subscription()->findPlanByLocalId($localPlanId);
```

### "Amounts look wrong"

Amounts are stored and sent in **minor units** while you pass **major units** to `pay()`,
`createPlan()`, `updateSubscriptionPlan()`, and `refund()`:

```text
You pass:      150.50
Stored/sent:   15050
```

Two exceptions to keep in mind:

* `subscribe()` builds the intention amount from `subscription_plans.minor_amount`
  (already minor units).
* `updateGatewaySubscription()` does **not** convert the amount — it forwards it as-is.

---

[← Previous: 7. Configuration](07-configuration.md) · [Next: 9. Advanced / Developer Documentation →](09-advanced.md)


