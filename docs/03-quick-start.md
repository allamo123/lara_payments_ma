# 3. Quick Start

[← Documentation index](README.md)

This is the shortest path to a working payment. Each step links to the full section if
you need more detail.

---

## Minimum setup

1. Install and migrate (see [2. Installation](02-installation.md)).
2. Set the credentials of the gateway you want to use in `.env`, for example for Paymob
   card payments:

```env
PAYMOB_API_KEY=...
PAYMOB_API_SECRET=...
PAYMOB_PUBLIC_KEY=...
PAYMOB_INTEGRATION_ID=...
PAYMOB_IFRAME_ID=...
PAYMOB_HMAC=...
PAYMOB_CURRENCY=EGP
```

All variables are listed in [7. Configuration](07-configuration.md).

---

## Make a normal payment

### Step 1 — Start the payment on your backend

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
        'source' => 'card',          // 'card' or 'wallet' for Paymob
    ]);

    // Redirect or embed $paylink for the customer.
    return redirect($paylink);
});
```

`pay()`:

* creates or reuses the local customer in `payment_customers`
* calls the gateway
* stores a local transaction in `payment_transactions` with status `pending`
* returns the **hosted payment URL** (Paymob) or a payment result array (Stripe)

For Stripe, the frontend first creates a Stripe PaymentMethod and sends its ID:

```php
$stripe = MaPayment::driver('stripe');

$result = $stripe->pay([
    'amount' => 150.50,
    'currency' => 'USD',
    'customer' => [
        'id' => auth()->id(),
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+201000000000',
    ],
    'source' => 'card',
    'payment_method' => ['id' => $paymentMethodId],
]);
```

### Step 2 — Let the customer pay

For Paymob the customer completes the payment inside the hosted iframe / wallet page
you redirected them to. For Stripe the PaymentIntent is confirmed during `pay()`.

### Step 3 — Handle the gateway callback

The package does **not** register routes for you:

```php
Route::post('/paymob/callback', function (Illuminate\Http\Request $request) {
    return response()->json(
        MaPayment::driver('paymob')->verify($request->all())
    );
});
```

`verify()` validates the callback and updates the local transaction status.

### Step 4 — Read the payment result

```php
$transactions = MaPayment::driver('paymob')->getTransactions('succeeded');
```

See [4. Payments](04-payments.md) for the complete payment documentation.

---

## Make a subscription payment (Paymob)

Subscriptions require one extra configuration value:

```env
PAYMOB_MOTO_INTEGRATION_ID=...
PAYMOB_SUBSCRIPTION_WEBHOOK_URL=https://your-app.test/paymob/subscription/webhook
```

and a running queue worker (`php artisan queue:work`), because subscription lifecycle
callbacks are applied in a queued job.

### Step 1 — Create a plan (one time per product/price)

```php
use Ma\Payment\Facades\MaPayment;

$payment = MaPayment::driver('paymob');

$response = $payment->subscription()->createPlan([
    'name' => 'Premium Monthly',
    'frequency' => 1,
    'reminder_days' => 2,
    'retrial_days' => 3,
    'amount' => 100,                 // major units -> sent to Paymob as amount_cents
    'number_of_deductions' => 12,
]);

// $response is the raw Paymob response.
```

Creating the plan also stores a **local plan** in `subscription_plans`.

### Step 2 — Start the subscription for a customer

```php
$plan = $payment->subscription()->findPlanByLocalId($localPlanId);

$result = $payment->subscription()->subscribe($plan, [
    'user_id' => auth()->id(),
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '01010101010',
]);

$response = $result['transacrion'];   // raw Paymob intention response
$checkoutUrl = $result['payLink'];    // send the customer here

return redirect($checkoutUrl);
```

### Step 3 — Customer completes the initial payment

The customer pays inside Paymob's unified checkout. Paymob then sends the **initial
transaction callback** to the payment callback route (the one you call `verify()` from).

### Step 4 — Handle both callback types

```php
// Initial subscription transaction + normal payments
Route::post('/paymob/callback', function (Illuminate\Http\Request $request) {
    return response()->json(
        MaPayment::driver('paymob')->verify($request->all())
    );
});

// Subscription lifecycle webhooks (suspended / resumed / cancelled / updated)
Route::post('/paymob/subscription/webhook', function (Illuminate\Http\Request $request) {
    MaPayment::driver('paymob')->subscription()->lifeCycle($request->all());

    return response()->json(['received' => true]);
});
```

After the initial callback is processed, the local subscription exists with status
`active`, and the initial transaction is linked to it.

### Step 5 — Manage the subscription

```php
$subscription = $payment->subscription()->findSubscrptionByLocalId($localId);

$payment->subscription()->suspendSubscription($subscription->gateway_subscription_id);
$payment->subscription()->resumeSubscription($subscription->gateway_subscription_id);

$list = $payment->subscription()->paginateLocalSubscrptions(15);
```

Read [5. Subscriptions](05-subscriptions.md) before going live: it explains local vs
gateway identifiers, how lifecycle callbacks are applied, and what is stored locally.

---

[← Previous: 2. Installation](02-installation.md) · [Next: 4. Payments →](04-payments.md)

