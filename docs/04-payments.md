# 4. Payments

[← Documentation index](README.md)

Everything in this section is about **one-off payments**. Subscriptions are covered
separately in [5. Subscriptions](05-subscriptions.md).

**Contents**

* [Creating a Payment](#creating-a-payment)
* [Payment Response](#payment-response)
* [Payment Status](#payment-status)
* [Payment Callbacks / Webhooks](#payment-callbacks--webhooks)
* [Retry Payment](#retry-payment)
* [Refunds](#refunds)
* [Transactions](#transactions)
* [Saved Cards](#saved-cards)

---

## Creating a Payment

### Selecting a gateway

```php
use Ma\Payment\Facades\MaPayment;

$gateway = MaPayment::driver('stripe');   // or 'paymob'
```

Both gateways implement `Ma\Payment\Interfaces\PaymentGatewayInterface`:

```text
pay()
verify()
getTransactions()
getCustomerTransactions()
getGatewayTransactionByOrderId()
retryPayment()
refund()
```

### Payment data

A payment request contains the amount, the currency, customer information, and the
payment source:

```php
$result = $gateway->pay([
    'amount' => 150.50,              // major units
    'currency' => 'USD',
    'customer' => [
        'id' => $user->id,           // your application's user ID
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+201000000000',
    ],
    'source' => 'card',              // Paymob also supports 'wallet'
    'payment_method' => [            // Stripe only
        'id' => 'pm_...',
    ],
]);
```

| Key                            | Required       | Notes                                                                 |
| ------------------------------ | -------------- | --------------------------------------------------------------------- |
| `amount`                       | ✅             | Major units (`150.50`). Must be greater than `0`.                      |
| `currency`                     | ✅             | Currency code (`EGP`, `USD`, …).                                       |
| `customer.id`                  | ✅             | Your application user ID; used as the local customer mapping key.      |
| `customer.first_name`          | ✅             |                                                                        |
| `customer.last_name`           | ✅             |                                                                        |
| `customer.email`               | ✅             | Validated email address.                                               |
| `customer.phone`               | ✅             |                                                                        |
| `customer.gateway_customer_id` | Optional       | Existing gateway customer ID, when you already have one.               |
| `source`                       | ✅             | `card` or (Paymob) `wallet`.                                           |
| `payment_method.id`            | Required for Stripe | Stripe PaymentMethod created by your frontend.                    |
| `id`                           | Retry only     | Local transaction ID — see [Retry Payment](#retry-payment).             |

Amounts are passed as **major units** and converted to **minor units** for the gateway
and for the database:

```text
150.50  →  15050 minor units
```

### Paymob card payment

Paymob card payments use a hosted iframe. `pay()` returns the iframe URL:

```php
$paymob = MaPayment::driver('paymob');

$paylink = $paymob->pay([
    'amount' => 150.50,
    'currency' => 'EGP',
    'customer' => [
        'id' => $user->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '01010101010',
    ],
    'source' => 'card',
]);

return redirect($paylink);
```

The flow:

```text
pay()
  ├── authenticate with Paymob
  ├── create order
  ├── create payment key
  └── build iframe URL
          │
          ▼
   customer pays in iframe
          │
          ▼
   Paymob callback → verify()
```

The local transaction is stored with status `pending` and is updated when the callback
is verified.

### Paymob wallet payment

Wallet payments are selected with `'source' => 'wallet'` and return the provider
redirect URL instead of an iframe URL:

```php
$walletUrl = $paymob->pay([
    'amount' => 200,
    'currency' => 'EGP',
    'customer' => [
        'id' => $user->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '01010101010',      // used as the wallet identifier
    ],
    'source' => 'wallet',
]);

return redirect($walletUrl);
```

Wallet payments require:

```env
PAYMOB_WALLET_INTEGRATION_ID=...
```

### Stripe card payment

Stripe card payments use Stripe PaymentIntents. Your frontend creates a Stripe
PaymentMethod and sends its ID to your backend:

```php
$stripe = MaPayment::driver('stripe');

$result = $stripe->pay([
    'amount' => 150.50,
    'currency' => 'USD',
    'customer' => [
        'id' => $user->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+201000000000',
    ],
    'source' => 'card',
    'payment_method' => ['id' => $paymentMethodId],
]);
```

The backend:

1. validates the request and builds `PaymentRequestDTO`
2. creates or reuses the local customer
3. creates the Stripe customer when required
4. creates and confirms the PaymentIntent
5. persists the local transaction
6. returns the payment result array

If Stripe declines the card, a `Stripe\Exception\CardException` is thrown **and** the
declined attempt is still persisted locally with the decline code as its status. See
[Payment Status](#payment-status) and [8. Troubleshooting](08-troubleshooting.md).

### Optional Stripe Blade card component

The package ships an optional Blade component (`ma-payment::Stripe.card`) that mounts
Stripe Elements. It is **never required** — the backend works with any frontend.

```bash
php artisan vendor:publish --tag=ma-payment-views
```

```blade
<x-ma-payment::Stripe.card
    :amount="$amount"
    :currency="$currency"
    :customer="$customer"
    :source="$source"
    :success-url="$successUrl"
    :payment-url="$paymentUrl ?? $retryUrl"
    :publishable-key="$publishableKey"
/>
```

| Property         | Required | Description                   |
| ---------------- | :------: | ----------------------------- |
| `publishableKey` |     ✅    | Stripe publishable key        |
| `paymentUrl`     |     ✅    | Backend payment endpoint      |
| `retryUrl`       | Optional | Backend retry endpoint        |
| `successUrl`     |     ✅    | Successful payment redirect   |
| `amount`         |     ✅    | Payment amount in major units |
| `currency`       |     ✅    | Currency code                 |
| `customer`       |     ✅    | Customer information          |
| `source`         |     ✅    | Payment source                |

The component mounts Stripe Elements, creates a PaymentMethod client-side, posts the
PaymentMethod ID to your backend, displays payment errors, and redirects on success.

The published JavaScript asset is available at:

```text
public/js/vendor/ma_payment/stripe/MaPaymentStripe.js
```

For a non-Blade frontend (React/Vue/Angular/vanilla JS/mobile), use the same
backend endpoints: create the PaymentMethod with Stripe.js on the client, then send its
ID to your `pay()` endpoint.

---

## Payment Response

The response shape depends on the gateway.

### Paymob

`pay()` returns a **string**: the hosted payment URL.

```php
$paylink = $paymob->pay([...]);   // string
```

* `source: 'card'` → iframe URL (`.../acceptance/iframes/<iframe-id>?payment_token=...`)
* `source: 'wallet'` → provider redirect URL

Redirect the customer to that URL (or embed the iframe URL).

### Stripe

`pay()` returns an **array** with the confirmed PaymentIntent summary:

```php
[
    'status' => 'succeeded',
    'minor_amount' => 15050,
    'amount_received' => 150.5,
]
```

### Local persistence

Regardless of gateway, `pay()` persists a local transaction through
`Ma\Payment\Models\PaymentTransaction` before returning. The raw gateway response is
stored in `meta_data` for reconciliation and debugging.

---

## Payment Status

Gateway-specific statuses are normalized into
`Ma\Payment\Enums\PaymentStatus`:

```text
pending
processing
succeeded
failed
canceled
fully_refunded
partially_refunded
```

Mapping examples:

| Gateway value | Normalized status               |
| ------------- | ------------------------------- |
| `approved`    | `PaymentStatus::SUCCEEDED`      |
| `succeeded`   | `PaymentStatus::SUCCEEDED`      |
| `paid`        | `PaymentStatus::SUCCEEDED`      |
| `success`     | `PaymentStatus::SUCCEEDED`      |
| `pending`     | `PaymentStatus::PENDING`        |
| `processing`  | `PaymentStatus::PENDING`        |
| `unpaid`      | `PaymentStatus::PENDING`        |
| `failed`      | `PaymentStatus::FAILED`         |
| `declined`    | `PaymentStatus::FAILED`         |
| `canceled`    | `PaymentStatus::CANCELED`       |
| `refunded`    | `PaymentStatus::FULLY_REFUNDED` |
| `partially_refunded` | `PaymentStatus::PARTIALLY_REFUNDED` |

Unknown values fall back to `PaymentStatus::FAILED`.

Reading the status of a stored transaction:

```php
$transaction = MaPayment::driver('paymob')->getTransactions()[0];

$transaction->status;   // 'succeeded'
```

---

## Payment Callbacks / Webhooks

The package **does not register routes or controllers**. Your application owns the
endpoint and forwards the payload to the gateway.

### Stripe webhook

```php
use Illuminate\Http\Request;
use Ma\Payment\Facades\MaPayment;

Route::post('/stripe/webhook', function (Request $request) {
    $gateway = MaPayment::driver('stripe');

    return response()->json(
        $gateway->verify(
            $request->getContent(),
            $request->header('Stripe-Signature')
        )
    );
});
```

`verify()` for Stripe:

* requires the **raw body as a string** — anything else throws
  `InvalidArgumentException('Stripe webhook payload must be a string.')`
* requires the `Stripe-Signature` header — a missing signature throws
  `InvalidArgumentException('Stripe webhook signature is required.')`
* verifies the signature with `STRIPE_WEBHOOK_SECRET`
* returns `['handled' => false, 'event_type' => ...]` for events the package does not handle
* throws `TransactionNotFoundException` when no local transaction matches the gateway
  reference in the event

Handled events:

```text
payment_intent.succeeded
payment_intent.payment_failed
payment_intent.canceled
refund.created
charge.refunded
```

* `refund.created` creates the refund child record in `refunded_payment_transactions`.
* `charge.refunded` updates the parent transaction (status and remaining refundable
  amount) and, when the event carries a refund ID, dispatches
  `Ma\Payment\Jobs\UpdateRefundTransactionJob` so the refund record is updated
  asynchronously.

```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

#### Why `UpdateRefundTransactionJob` exists

Stripe can deliver `refund.created` and `charge.refunded` independently, so
`charge.refunded` may arrive before the refund record exists. The job:

* loads the refund with `RefundTransactionRepository` (row locked)
* throws `Ma\Payment\Exceptions\RefundTransactionNotFoundException` if it is not there yet
* lets Laravel retry the job: `$tries = 5`, `backoff() = [2, 4, 5, 6, 7]` seconds
* updates `refund_type` once the record exists

**Requires a queue worker** (`php artisan queue:work`).

### Paymob callback

```php
use Illuminate\Http\Request;
use Ma\Payment\Facades\MaPayment;

Route::post('/paymob/callback', function (Request $request) {
    $gateway = MaPayment::driver('paymob');

    return response()->json($gateway->verify($request->all()));
});
```

`verify()` for Paymob:

1. accepts the payload as an array or a JSON string
2. decides which handler to use:
   * payload contains an `intention` key → **subscription transaction callback**
     (see [5. Subscriptions → Subscription Callbacks](05-subscriptions.md))
   * otherwise → normal payment callback
3. verifies the callback HMAC using `PAYMOB_HMAC`; an invalid HMAC throws
   `RuntimeException('Invalid Paymob transaction HMAC.')`
4. looks up the local transaction by gateway order ID
5. throws `TransactionNotFoundException` when the transaction is unknown
6. throws `TransactionAlreadyProccessedException` when the transaction is no longer `pending`
7. updates the transaction in a database transaction and returns the result

For a normal (non-subscription) callback the return value is:

```php
['order' => [/* the Paymob order object */]]
```

For a Paymob `TOKEN` callback (saved card token) the gateway saves a card record and
returns the stored card attributes — see [Saved Cards](#saved-cards).

`REQUIRED`:

```env
PAYMOB_HMAC=...
```

### CSRF

If your callback routes live inside a CSRF-protected group, exclude those endpoints:

```php
->withoutMiddleware([VerifyCsrfToken::class])
```

Only disable CSRF protection for the callback endpoints.

---

## Retry Payment

```php
$gateway->retryPayment($localTransactionId, $paymentMethodId = null);
```

Retries are gateway-specific but exposed through the same method.

### Paymob

```php
$paylink = $paymob->retryPayment($localTransactionId);

return redirect($paylink);
```

* The **local transaction ID** is passed (not the gateway reference).
* Paymob creates a new payment attempt and returns a new payment link.
* The same local transaction row is reused (it is updated, not duplicated).
* Retry is allowed when the transaction status is `failed` or `pending`.

If the transaction status is anything else **and** its source is not
`card_subscription`, `Ma\Payment\Exceptions\TransactionCannotProcessException` is thrown.

### Stripe

```php
$result = $stripe->retryPayment($localTransactionId, $paymentMethodId);
```

* The PaymentIntent is re-confirmed with a new PaymentMethod, so the payment method ID
  is **required** here.
* Returns the same array shape as `pay()` for Stripe
  (`status`, `minor_amount`, `amount_received`).

---

## Refunds

Both gateways expose refunds through:

```php
$gateway->refund($localTransactionId, $amount);
```

* `$localTransactionId` is the **local** transaction ID.
* `$amount` is in **major units** and is converted to minor units for the gateway.

```php
$gateway->refund($transactionId, 50);   // refund 50.00
```

### Rules enforced by the package

1. The transaction must exist, otherwise
   `Ma\Payment\Exceptions\TransactionNotFoundException` is thrown.
2. The refund must not exceed `remain_minor_amount`, otherwise
   `Ma\Payment\Exceptions\RefundAmountGreaterThanTransactionAmountException` is thrown.
3. The gateway response must reference the same order/transaction as the local record,
   otherwise `GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException` or
   `GatewayTxnIdAndLocalTxnIdNotSameException` is thrown.
4. A refund record is written to `refunded_payment_transactions`.

### What happens to the local transaction

* `remain_minor_amount` is reduced by the refunded amount.
* When the remaining amount reaches `0`, the parent transaction status is set to
  `fully_refunded`.
* Otherwise the parent transaction status is taken from the gateway's order status for
  that refund, and the remaining refundable amount reflects the part that is still
  refundable.

Partial refunds can be repeated until the remaining amount reaches zero:

```text
Original transaction: 10.00   (remain_minor_amount = 1000)

Refund 1: 3.00                (remain_minor_amount = 700)
Refund 2: 2.00                (remain_minor_amount = 500)
Refund 3: 5.00                (remain_minor_amount = 0  → fully_refunded)
```

> **Gateway differences.** For Paymob, `refund()` updates the local transaction and the
> refund record immediately. For Stripe, `refund()` creates the refund at Stripe and
> validates that the returned PaymentIntent matches the local transaction; the local
> status and remaining amounts are updated by the Stripe webhooks
> (`refund.created`, `charge.refunded`).

### Where refunds are stored

```text
refunded_payment_transactions
```

Each refund row belongs to the parent transaction through `parent_transaction` →
`payment_transactions.gateway_reference`:

```php
$transaction->refundedPayments;   // has-many relation
```

> There is no subscription-specific refund API. `refund()` operates on a local
> transaction ID and the current implementation does not restrict it by transaction
> type, so the initial subscription transaction can be refunded through the same
> method. Subscription-specific refund semantics are not implemented by the package.

---

## Transactions

Every payment attempt is stored in:

```text
payment_transactions
```

### Listing and filtering

Both gateways implement:

```php
public function getTransactions(?string $status = null): Illuminate\Database\Eloquent\Collection;

public function getCustomerTransactions(int $userId, ?string $status = null): Illuminate\Database\Eloquent\Collection;
```

```php
use Ma\Payment\Facades\MaPayment;

$gateway = MaPayment::driver('stripe');   // or 'paymob'

// All transactions
$transactions = $gateway->getTransactions();

// Only succeeded transactions
$transactions = $gateway->getTransactions('succeeded');

// Transactions of one application user
$transactions = $gateway->getCustomerTransactions($userId);

// Only refunded transactions of that user
$transactions = $gateway->getCustomerTransactions($userId, 'fully_refunded');
```

* `$status` is an exact-match filter on the `status` column and takes a
  `PaymentStatus` value (`pending`, `processing`, `succeeded`, `failed`, `canceled`,
  `fully_refunded`, `partially_refunded`). An unknown value simply returns an empty
  collection — it is not validated against the enum.
* `getCustomerTransactions()` resolves the user through the local `payment_customers`
  mapping and throws `Ma\Payment\Exceptions\CustomerNotFoundException` when no mapping
  exists.
* Filtering by gateway, date range, order ID, or gateway reference is **not** exposed.
  Retrieve and filter in your application if you need more:

```php
$transactions = $gateway
    ->getCustomerTransactions($userId, 'succeeded')
    ->filter(fn ($transaction) => $transaction->gateway === 'stripe');
```

### Returned model

Both methods return a collection of `Ma\Payment\Models\PaymentTransaction`. There is no
pagination — the full result set is loaded.

| Field                           | Description                                                       |
| ------------------------------- | ----------------------------------------------------------------- |
| `gateway`                       | Gateway name (`stripe`, `paymob`)                                  |
| `order_id`                      | Gateway order identifier                                           |
| `customer_id`                   | Local package customer ID                                          |
| `gateway_reference`             | Gateway transaction reference                                      |
| `minor_amount`                  | Original amount in minor units                                     |
| `remain_minor_amount`           | Remaining refundable amount in minor units                         |
| `currency`                      | Currency code                                                      |
| `subscription_id`               | Local subscription ID — `null` for non-subscription transactions   |
| `subscription_transaction_type` | `initial` or `renewal` — `null` for non-subscription transactions  |
| `status`                        | Normalized payment status                                          |
| `source`                        | Payment source (`card`, `wallet`, `card_subscription`)              |
| `source_subtype`                | Source subtype (for example the card brand)                        |
| `meta_data`                     | Raw gateway response                                               |

Relations:

* `customer()` → `Ma\Payment\Models\PaymentCustomer`
* `refundedPayments()` → `Ma\Payment\Models\RefundedPaymentTransaction` (matched by `gateway_reference`)
* `subscription()` → `Ma\Payment\Models\Subscription` (see [5. Subscriptions](05-subscriptions.md))

Amounts are stored in minor units, so major units are obtained by dividing by 100:

```php
foreach ($gateway->getCustomerTransactions(auth()->id(), 'succeeded') as $transaction) {
    $transaction->gateway_reference;     // e.g. Stripe PaymentIntent ID
    $transaction->minor_amount;          // e.g. 15050
    $transaction->minor_amount / 100;    // 150.5
    $transaction->status;                // 'succeeded'
    $transaction->refundedPayments;      // related refund records
}
```

`getGatewayTransactionByOrderId(int $orderId)` is also part of the gateway contract. It
returns the gateway-side order/transaction payload for a gateway order ID and is
implemented by Paymob; the Stripe implementation currently returns nothing
(unimplemented placeholder).

---

## Saved Cards

The package stores **saved card records** in:

```text
customer_cards
```

| Column            | Description                  |
| ----------------- | ---------------------------- |
| `customer_id`     | Local customer ID            |
| `gateway`         | Gateway name                 |
| `gateway_card_id` | Gateway card/token identifier |
| `token`           | Card token                   |
| `brand`           | Card brand                   |
| `last_four`       | Last four digits             |
| `expiry_month`    | Expiry month                 |
| `expiry_year`     | Expiry year                  |
| `cardholder_name` | Cardholder name              |
| `metadata`        | Raw gateway card payload     |

A card record is stored when Paymob sends a **`TOKEN`** callback. The gateway's
`verify()` handles that callback type, resolves the local customer **by email**, stores
the card (unique per `gateway` + `gateway_card_id`), and returns the stored attributes:

```php
use Ma\Payment\Facades\MaPayment;

Route::post('/paymob/callback', function (Illuminate\Http\Request $request) {
    return response()->json(
        MaPayment::driver('paymob')->verify($request->all())
    );
});
```

The relation is available on the customer:

```php
$customer->cards;   // HasMany Ma\Payment\Models\CustomerCard
```

> **What is not implemented today:** there is no public method to *list* saved cards, to
> *delete* one, or to *charge* a stored card through the package API. The card records
> exist locally as the `CustomerCard` model (`customer_cards` table). The Stripe gateway
> does not store cards.

---

[← Previous: 3. Quick Start](03-quick-start.md) · [Next: 5. Subscriptions →](05-subscriptions.md)





