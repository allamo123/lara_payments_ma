<p align="center"><img src="./logo.png" width="300"></p>
<p align="center">
    <img alt="GitHub License" src="https://img.shields.io/github/license/allamo123/laravel-grapes?style=flat&label=license">
    <img alt="Packagist Downloads" src="https://img.shields.io/packagist/dm/ma-lara/payments?style=flat&label=downloads">
    <img alt="GitHub Release" src="https://img.shields.io/github/v/release/allamo123/laravel-grapes?include_prereleases&style=flat">
</p>

<h1 align="center">MA Lara Payment</h1>

<p align="center">
    A unified Laravel payment package for integrating multiple payment gateways
    through a consistent API.
</p>

<p align="center">
    <a href="#documentation">Documentation</a>
    &nbsp;&nbsp;•&nbsp;&nbsp;
    <a href="#testing">Testing</a>
    &nbsp;&nbsp;•&nbsp;&nbsp;
    <a href="#contributing">Contributing</a>
    &nbsp;&nbsp;•&nbsp;&nbsp;
    <a href="#license">License</a>
</p>

---

## Documentation

**ma-lara/payments** provides a unified API for integrating multiple payment gateways into Laravel applications.

The package exposes common operations such as:

* `pay()`
* `verify()`
* `retryPayment()`
* `refund()`
* Transaction queries

Each gateway implements the package's gateway contract while keeping provider-specific API calls, authentication, response mapping, and webhook handling isolated inside the gateway implementation.

### Supported Gateways

| Gateway | Card | Wallet | Retry | Refund | Webhook / Callback |
| ------- | :--: | :----: | :---: | :----: | :----------------: |
| Stripe  |   ✅  |    ❌   |   ✅   |    ✅   |      ✅ Signed      |
| Paymob  |   ✅  |    ✅   |   ✅   |    ✅   |       ✅ HMAC       |

### Important

The package backend is **frontend-agnostic**.

The package includes an optional Stripe Blade card component, but you can integrate the backend with:

* Blade
* React
* Vue
* Angular
* Vanilla JavaScript
* Mobile applications
* Any frontend capable of communicating with your backend API

---

## Features

* Unified payment gateway contract.
* Runtime gateway selection through `driver()`.
* Stripe card payments using PaymentIntents.
* Paymob card payments using a hosted iframe.
* Paymob mobile-wallet payments.
* Payment retries.
* Full and partial refunds.
* Stripe signed webhook verification.
* Paymob HMAC callback verification.
* Local customer persistence.
* Local transaction persistence.
* Local refund persistence.
* Normalized `PaymentStatus` enum.
* Minor-unit monetary storage.
* Raw gateway responses stored in `meta_data`.
* Gateway-specific API services.
* Gateway-specific webhook handlers.
* Repository-based persistence.
* DTOs and value objects for important boundaries.
* Optional Stripe Blade card-payment component.
* Extensible architecture for adding additional gateways.

---

# Requirements

The package requirements are defined by `composer.json`.

| Requirement | Version             |
| ----------- | ------------------- |
| PHP         | `>=8.1`             |
| Laravel     | `>=9.0`             |
| JSON        | `ext-json`          |
| cURL        | `ext-curl`          |
| Stripe      | `stripe/stripe-php` |

> The package targets the current development version documented by this README.

---

# Installation

Install the package through Composer:

```bash
composer require ma-lara/payments
```

Laravel package discovery automatically registers the package service provider and facade.

### Publish Configuration

```bash
php artisan vendor:publish --tag=ma-payment-config
```

### Publish Views and Frontend Assets

```bash
php artisan vendor:publish --tag=ma-payment-views
```

### Publish Migration Files

```bash
php artisan vendor:publish --tag=ma-payment-views
```

### Run Migrations

```bash
php artisan migrate
```

The package provides three main tables:

| Table                           | Purpose                                              |
| ------------------------------- | ---------------------------------------------------- |
| `payment_customers`             | Maps application users to gateway customers          |
| `payment_transactions`          | Stores payment attempts and their gateway references |
| `refunded_payment_transactions` | Stores refund transactions                           |

---

# Configuration

The package configuration is available at:

```text
config/ma_payment_conf.php
```

The gateway registry is available at:

```text
config/ma_payment_drivers.php
```

## Environment Variables

### Stripe

```env
STRIPE_API_SECRET=sk_...
STRIPE_API_KEY=pk_...
STRIPE_BASE_URL=https://api.stripe.com
STRIPE_CURRENCY=USD
STRIPE_WEBHOOK_SECRET=whsec_...
```

| Variable                | Purpose                       |
| ----------------------- | ----------------------------- |
| `STRIPE_API_SECRET`     | Stripe secret API key         |
| `STRIPE_API_KEY`        | Stripe publishable key        |
| `STRIPE_BASE_URL`       | Stripe API base URL           |
| `STRIPE_CURRENCY`       | Default Stripe currency       |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret |

### Paymob

```env
PAYMOB_API_KEY=...
PAYMOB_API_SECRET=...
PAYMOB_INTEGRATION_ID=...
PAYMOB_WALLET_INTEGRATION_ID=...
PAYMOB_IFRAME_ID=...
PAYMOB_HMAC=...
PAYMOB_CURRENCY=EGP
```

| Variable                       | Purpose                               |
| ------------------------------ | ------------------------------------- |
| `PAYMOB_API_KEY`               | Paymob API key                        |
| `PAYMOB_API_SECRET`            | Paymob API secret used where required |
| `PAYMOB_INTEGRATION_ID`        | Card integration ID                   |
| `PAYMOB_WALLET_INTEGRATION_ID` | Wallet integration ID                 |
| `PAYMOB_IFRAME_ID`             | Paymob card iframe ID                 |
| `PAYMOB_HMAC`                  | Callback HMAC secret                  |
| `PAYMOB_CURRENCY`              | Default Paymob currency               |


---

# Gateway Registry

Gateways are registered through:

```text
config/ma_payment_drivers.php
```

Example:

```php
return [
    'stripe' => \Ma\Payment\Gateways\Stripe\StripeGateway::class,
    'paymob' => \Ma\Payment\Gateways\Paymob\PaymobGateway::class,
];
```

The manager and factory use this registry to resolve the requested gateway.

---

# Quick Start

## Selecting a Gateway

Use `PaymentGatewayManager` to select a gateway:

```php
use Ma\Payment\PaymentGatewayManager;

$gateway = app(PaymentGatewayManager::class)
    ->driver('stripe');
```

You can then call the common gateway operations:

```php
$gateway->pay(...);

$gateway->verify(...);

$gateway->retryPayment(...);

$gateway->refund(...);
```

The same architecture is used for Paymob:

```php
$gateway = app(PaymentGatewayManager::class)
    ->driver('paymob');
```

---

# Payment Data

A payment request contains the payment amount, currency, customer information, and gateway-specific payment information.

Example:

```php
$result = $gateway->pay([
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

    'payment_method' => [
        'id' => 'pm_...',
    ],
]);
```

### Amounts

Application-facing payment amounts use **major units**:

```php
150.50
```

The package converts monetary values to **minor units** for gateway communication and persistence:

```text
150.50 USD → 15050 cents
```

Transactions store amounts in minor units.

---

# Payment Flow

The general payment architecture is:

```text
Application
    │
    │ pay([...])
    ▼
MaPayment Facade
    │
    ▼
PaymentGatewayManager
    │
    ▼
PaymentGatewayFactory
    │
    ▼
PaymentGatewayInterface
    │
    ├───────────────┐
    ▼               ▼
 Stripe           Paymob
    │               │
    ▼               ▼
Gateway API      Gateway API
    │               │
    └───────┬───────┘
            ▼
    PaymentTransaction
            │
            ▼
     Webhook / Callback
            │
            ▼
     PaymentStatus
```

The common payment workflow is implemented by `BaseGateway`.

Conceptually:

```text
Payment Request
      │
      ▼
Validate / Build DTO
      │
      ▼
Get or create local customer
      │
      ▼
Ensure gateway customer
      │
      ▼
Call gateway API
      │
      ▼
Build PaymentTransactionDTO
      │
      ▼
Persist transaction
      │
      ▼
Return payment result
```

Gateway implementations provide the provider-specific operations while the shared workflow remains in the base gateway.

---

# Payment Status

The package normalizes gateway-specific statuses into:

```php
Ma\Payment\Enums\PaymentStatus
```

Supported states include:

```text
pending
processing
succeeded
failed
canceled
partially_refunded
fully_refunded
```

Gateway-specific status values are mapped into these common states.

For example:

```text
Stripe: succeeded
Paymob: success
Gateway-specific: approved

        ↓

PaymentStatus::SUCCEEDED
```

This allows the application to work with a common status model regardless of the selected gateway.

---

# Stripe

## Capabilities

| Operation        | Supported |
| ---------------- | :-------: |
| Card payment     |     ✅     |
| PaymentIntent    |     ✅     |
| Gateway customer |     ✅     |
| Retry payment    |     ✅     |
| Full refund      |     ✅     |
| Partial refund   |     ✅     |
| Signed webhook   |     ✅     |
| Wallet payment   |     ❌     |
| Capture          |     ❌     |
| Void             |     ❌     |

---

## Stripe Card Payment

Stripe card payments use Stripe PaymentIntents.

The frontend creates a Stripe PaymentMethod and sends its ID to your backend.

```php
$result = $gateway->pay([
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

    'payment_method' => [
        'id' => 'pm_...',
    ],
]);
```

The backend then:

1. Validates the payment request.
2. Creates or updates the local customer.
3. Creates the Stripe customer when required.
4. Creates the PaymentIntent.
5. Confirms the PaymentIntent.
6. Persists the local transaction.
7. Returns the payment result.

---

# Stripe Blade Card Component

The package provides an optional Blade component for Stripe card payments.

It is **not required** to use the Stripe gateway.

View:

```text
ma-payment::Stripe.card
```

The component uses Stripe Elements and Stripe.js to collect the customer's card details.

## Publishing the Component

```bash
php artisan vendor:publish --tag=ma-payment-views
```

The published JavaScript asset is available under:

```text
public/js/vendor/ma_payment/stripe/MaPaymentStripe.js
```

## Component Example

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

### Component Properties

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

The component:

1. Mounts Stripe Elements.
2. Collects card information.
3. Creates a Stripe PaymentMethod.
4. Sends the PaymentMethod ID to your backend.
5. Displays payment errors.
6. Redirects after successful payment.

---

# Stripe With Other Frontends

The Stripe Blade component is only a convenience feature.

You can use the same backend API with:

```text
Blade
React
Vue
Angular
Vanilla JavaScript
React Native
Mobile applications
Other frontends
```

The frontend flow is:

```text
Frontend
   │
   │ Stripe.js
   ▼
Create PaymentMethod
   │
   ▼
Your Backend
   │
   │ pay()
   ▼
ma-lara/payments
   │
   ▼
Stripe API
```

For example, a React or Vue application can create a Stripe PaymentMethod and send its ID to a Laravel endpoint that calls:

```php
$gateway->pay([
    // ...
    'payment_method' => [
        'id' => $paymentMethodId,
    ],
]);
```

The package does not require the frontend to use Blade.

---

# Stripe Retry

Failed Stripe payments can be retried by re-confirming the existing PaymentIntent with a new PaymentMethod.

```php
$gateway->retryPayment(
    $transactionId,
    $paymentMethodId
);
```

The retry operation remains gateway-specific while being exposed through the common gateway contract.

---

# Paymob

## Capabilities

| Operation                  | Supported |
| -------------------------- | :-------: |
| Card payment               |     ✅     |
| Hosted iframe              |     ✅     |
| Mobile wallet              |     ✅     |
| Retry payment              |     ✅     |
| Full refund                |     ✅     |
| Partial refund             |     ✅     |
| HMAC callback verification |     ✅     |
| Gateway transaction lookup |     ✅     |
| Capture                    |     ❌     |
| Void                       |     ❌     |

---

# Paymob Card Payment

Paymob card payments use a hosted checkout iframe.

```php
$paylink = $gateway->pay([
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
```

The payment flow is:

```text
Application
    │
    ▼
Paymob pay()
    │
    ├── Authentication
    ├── Create order
    ├── Create payment key
    └── Generate iframe URL
            │
            ▼
       Hosted iframe
            │
            ▼
      Customer payment
            │
            ▼
       Paymob callback
            │
            ▼
          verify()
```

A new local transaction is initially stored as:

```text
pending
```

The callback later determines the final transaction status.

---

# Paymob Wallet Payment

Wallet payments are selected using:

```php
'source' => 'wallet'
```

Example:

```php
$paylink = $gateway->pay([
    'amount' => 200,
    'currency' => 'EGP',

    'customer' => [
        'id' => $user->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '01010101010',
    ],

    'source' => 'wallet',
]);
```

The customer's phone number is used as the wallet identifier.

The Paymob wallet integration uses:

```env
PAYMOB_WALLET_INTEGRATION_ID=...
```

The resulting payment response contains the provider redirect URL.

Your application can redirect the customer to that URL.

---

# Paymob Callback Verification

Paymob callbacks must be verified before updating a transaction.

Example:

```php
$transaction = $gateway->verify(
    $request->all()
);
```

The verification flow is:

```text
Paymob Callback
      │
      ▼
Verify HMAC
      │
      ├── Invalid → Reject
      │
      ▼
Find Local Transaction
      │
      ▼
Check Transaction State
      │
      ▼
Update Transaction
      │
      ▼
Return Verification Result
```

The callback signature is verified using the configured:

```env
PAYMOB_HMAC=...
```

An invalid signature must prevent the transaction from being processed.

Applications should expose their own callback route and delegate the callback payload to the gateway.

---

# Webhooks and Callbacks

The package does **not** register application routes or controllers automatically.

Your Laravel application owns the HTTP endpoint.

The application then delegates the payload to the appropriate gateway handler.

---

## Stripe Webhook

Example:

```php
use Illuminate\Http\Request;
use Ma\Payment\Gateways\Stripe\Services\StripeWebhookHandler;

Route::post('/stripe/webhook', function (
    Request $request,
    StripeWebhookHandler $handler
) {
    return response()->json(
        $handler->handle(
            $request->getContent(),
            $request->header('Stripe-Signature')
        )
    );
});
```

The handler verifies the Stripe signature using:

```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

Handled events include:

```text
payment_intent.succeeded
payment_intent.payment_failed
payment_intent.canceled
refund.created
charge.refunded
```

Unhandled events return an appropriate unhandled result rather than being processed as payment events.

### CSRF

If the webhook endpoint is registered under a CSRF-protected route group, configure the endpoint appropriately for your application.

For example:

```php
->withoutMiddleware([VerifyCsrfToken::class])
```

Only disable CSRF protection for the webhook endpoint where appropriate.

---

## Paymob Callback

Paymob callbacks are handled by your application's callback route.

Example:

```php
Route::post('/paymob/callback', function (Request $request) {
    $gateway = app(PaymentGatewayManager::class)
        ->driver('paymob');

    return response()->json(
        $gateway->verify($request->all())
    );
});
```

The gateway verifies the HMAC before processing the transaction.

---

# Transaction Processing

Transactions are stored in:

```text
payment_transactions
```

A transaction contains information such as:

* Local transaction ID
* Customer ID
* Gateway
* Gateway reference
* Amount
* Remaining refundable amount
* Currency
* Status
* Gateway metadata

Gateway responses are stored in:

```text
meta_data
```

This allows applications to retain the original gateway response for reconciliation and debugging.

---

# Refunds

Both Stripe and Paymob expose refunds through:

```php
$gateway->refund($transactionId, $amount);
```

Example:

```php
$gateway->refund($transactionId, 50);
```

The amount is provided in **major units**.

For example:

```text
Transaction: $150.50
Refund:      $50.00
Remaining:   $100.50
```

The package prevents refunds that exceed the remaining refundable amount.

### Refund Rules

The package validates:

1. The transaction exists.
2. The requested refund does not exceed the remaining amount.
3. The gateway transaction reference matches the local transaction.
4. The refund is persisted successfully.
5. The remaining refundable amount is updated.

Refund records are stored in:

```text
refunded_payment_transactions
```

---

# Partial Refunds

Partial refunds can be performed multiple times until the remaining amount reaches zero.

Example:

```text
Original transaction: $100

Refund 1: $30
Remaining: $70

Refund 2: $20
Remaining: $50

Refund 3: $50
Remaining: $0
```

The transaction status changes from:

```text
succeeded
```

to:

```text
partially_refunded
```

and finally:

```text
fully_refunded
```

---

# Capture

Capture is currently **not supported**.

There is no capture operation in:

```text
PaymentGatewayInterface
BaseGateway
StripeGateway
PaymobGateway
```

Stripe PaymentIntents used by the package are confirmed using the configured payment flow rather than exposing a separate authorization/capture operation.

---

# Void

Void is currently **not supported**.

The package does not expose an authorization-only → void lifecycle.

Paymob refunds are treated as refunds rather than as a separate void operation.

---

# Retry Payments

Retry behavior is gateway-specific but exposed through the common API:

```php
$gateway->retryPayment($transactionId);
```

### Stripe

Stripe retries the PaymentIntent using a new PaymentMethod.

### Paymob

Paymob creates a new payment attempt and returns a new payment link.

Retry operations should only be allowed for transaction states supported by the gateway implementation.

---

# Gateway Support Matrix

| Capability                 |      Stripe      |     Paymob    |
| -------------------------- | :--------------: | :-----------: |
| Card payment               |         ✅        |       ✅       |
| Wallet payment             |         ❌        |       ✅       |
| Retry payment              |         ✅        |       ✅       |
| Full refund                |         ✅        |       ✅       |
| Partial refund             |         ✅        |       ✅       |
| Capture                    |         ❌        |       ❌       |
| Void                       |         ❌        |       ❌       |
| Webhook / callback         |         ✅        |       ✅       |
| Signature verification     | Stripe Signature |      HMAC     |
| Gateway transaction lookup |      Limited     |       ✅       |
| Hosted card UI             |  Blade component | Paymob iframe |

---

# Error Handling

Package exceptions are located under:

```text
Ma\Payment\Exceptions
```

Common exceptions include:

| Exception                                             | Purpose                                              |
| ----------------------------------------------------- | ---------------------------------------------------- |
| `MissingPaymentInfoException`                         | Required payment information is missing              |
| `CustomerNotFoundException`                           | Customer cannot be found                             |
| `TransactionNotFoundException`                        | Transaction cannot be found                          |
| `TransactionAlreadyProccessedException`               | Transaction has already been processed               |
| `TransactionCannotProcessException`                   | Transaction cannot be processed in its current state |
| `TransactionFailedException`                          | Payment failed                                       |
| `GatewayTxnIdAndLocalTxnIdNotSameException`           | Gateway/local reference mismatch                     |
| `GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException` | Gateway/local order mismatch                         |
| `RefundAmountGreaterThanTransactionAmountException`   | Refund exceeds the remaining amount                  |
| `InvalidWebhookSignatureException`                    | Webhook/callback signature is invalid                |

Example:

```php
use Ma\Payment\Exceptions\RefundAmountGreaterThanTransactionAmountException;

try {
    $gateway->refund($transactionId, 999999);
} catch (RefundAmountGreaterThanTransactionAmountException $e) {
    // Handle invalid refund amount.
}
```

Stripe-specific SDK exceptions may also be exposed when the Stripe API rejects a request.

For example:

```php
use Stripe\Exception\CardException;
```

---

# Frontend Integration

The package does not require a specific frontend framework.

## Stripe

```text
Blade
React
Vue
Angular
Vanilla JS
Mobile
Other frontend
```

The frontend creates the Stripe PaymentMethod and sends the ID to your backend.

```text
Frontend
   │
   │ Stripe.js
   ▼
PaymentMethod
   │
   ▼
Laravel Endpoint
   │
   ▼
$gateway->pay()
   │
   ▼
Stripe
```

## Paymob

Paymob provides the payment interface through the provider:

```text
Card
  ↓
Hosted iframe

Wallet
  ↓
Provider redirect URL
```

Your application only needs to redirect or embed the returned payment URL.

---

# Architecture

The package uses a layered and extensible gateway architecture.

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
PaymentGatewayFactory
     │
     ▼
PaymentGatewayInterface
     │
     ├───────────────┐
     ▼               ▼
StripeGateway   PaymobGateway
     │               │
     ▼               ▼
StripeApiService PaymobApiService
     │               │
     ▼               ▼
Stripe API       Paymob API
```

The shared payment workflow is implemented by:

```text
BaseGateway
```

---

# Design Patterns

The package uses several established design patterns.

### Facade

```text
MaPayment
```

Provides a convenient entry point for the package.

### Factory

```text
PaymentGatewayFactory
```

Creates gateway implementations from the driver registry.

### Strategy

```text
PaymentGatewayInterface
```

Allows Stripe, Paymob, and future gateways to be interchangeable.

### Template Method

```text
BaseGateway::executePayment()
```

Defines the shared payment workflow while allowing gateways to implement gateway-specific operations.

### Repository

Repositories isolate persistence logic from gateway logic.

Examples:

```text
TransactionRepository
PaymentCustomerRepository
RefundTransactionRepository
```

### DTO

DTOs define structured boundaries between application, gateway, and persistence layers.

Examples:

```text
PaymentRequestDTO
PaymentTransactionDTO
```

### Value Objects

The package uses value objects for validated primitives such as:

```text
Money
UserEmail
```

---

# Core Components

## PaymentGatewayManager

Responsible for selecting a gateway driver.

```php
$manager->driver('stripe');
$manager->driver('paymob');
```

---

## PaymentGatewayFactory

Responsible for resolving a configured gateway implementation.

The factory reads:

```text
config/ma-drivers.php
```

and resolves the gateway through Laravel's service container.

---

## PaymentGatewayInterface

The gateway contract defines the common gateway API.

Typical operations include:

```text
pay()
verify()
getTransactions()
getCustomerTransactions()
getGatewayTransactionByOrderId()
retryPayment()
refund()
```

Gateway implementations may support different capabilities while maintaining the common contract.

---

## BaseGateway

`BaseGateway` contains the shared payment orchestration.

The main payment workflow is implemented by:

```php
executePayment()
```

The method coordinates:

```text
PaymentRequestDTO
      ↓
Customer handling
      ↓
Gateway customer handling
      ↓
Gateway API request
      ↓
PaymentTransactionDTO
      ↓
TransactionRepository
```

Gateway-specific classes should not duplicate this common workflow.

---

# Data Transfer Objects

## PaymentRequestDTO

Represents validated payment input.

It sits at the boundary between:

```text
Application → Package
```

It handles concepts such as:

* Amount
* Currency
* Customer
* Source
* Payment method

---

## PaymentTransactionDTO

Represents the data required to persist a payment transaction.

It sits at the boundary between:

```text
Gateway → Persistence
```

Gateway implementations map provider responses into this DTO before passing the data to the repository.

---

# Value Objects

## Money

Represents monetary values and provides controlled conversion between major and minor units.

Example:

```text
150.50
   ↓
15050 minor units
```

Financial amounts should be persisted using integer minor units rather than floating-point database values.

## UserEmail

Represents a validated customer email address.

---

# Repositories

The package uses repositories to isolate database persistence.

### TransactionRepository

Handles payment transaction persistence and queries.

### PaymentCustomerRepository

Handles gateway customer mappings.

### RefundTransactionRepository

Handles refund transaction persistence.

Database operations involving concurrent transaction updates use appropriate row locking where required.

---

# Database Relationships

The main relationships are:

```text
users
  │
  │ user_id
  ▼
payment_customers
  │
  │ customer_id
  ▼
payment_transactions
  │
  │ gateway_reference
  ▼
refunded_payment_transactions
```

A customer can have multiple payment transactions.

A payment transaction can have multiple refund records.

---

# Adding a New Gateway

Adding a new gateway should not require modifying the generic payment workflow.

For example, to add a `Tap` gateway:

```text
src/
└── Gateways/
    └── Tap/
        ├── TapGateway.php
        └── Services/
            ├── TapApiService.php
            └── TapWebhookHandler.php
```

## 1. Create the Gateway

```php
class TapGateway extends BaseGateway
    implements PaymentGatewayInterface
{
    // ...
}
```

## 2. Inject the Gateway API Service

```php
public function __construct(
    CustomerSerivce $customerService,
    TransactionRepositoryInterface $transactionRepository,
    private TapApiService $apiService,
) {
    parent::__construct(
        $customerService,
        $transactionRepository
    );

    $this->gateway_name = 'tap';
}
```

## 3. Implement the Gateway API Call

```php
protected function sendPaymentRequest(
    PaymentRequestDTO $dto
): array {
    return $this->apiService->charge($dto);
}
```

## 4. Map the Gateway Response

Build a:

```php
PaymentTransactionDTO
```

from the provider response.

Gateway-specific statuses should be normalized through the package status mapping.

## 5. Register the Driver

Add the gateway to:

```text
config/ma-drivers.php
```

```php
return [
    'stripe' => StripeGateway::class,
    'paymob' => PaymobGateway::class,
    'tap' => TapGateway::class,
];
```

The manager and factory do not need gateway-specific changes.

## 6. Add Configuration

Add provider credentials and gateway-specific configuration to:

```text
config/ma-payment.php
```

Use environment variables for secrets and credentials.

## 7. Add Webhook Handling

If the gateway supports webhooks:

```text
TapWebhookHandler
```

should be responsible for:

1. Signature verification.
2. Event parsing.
3. Event identification.
4. Transaction lookup.
5. Status mapping.
6. Safe transaction updates.

## 8. Add Tests

A new gateway should have tests covering:

* Successful payment.
* Failed payment.
* Invalid payment data.
* Verification.
* Invalid signature.
* Duplicate callback.
* Refund.
* Partial refund.
* Over-refund.
* Gateway-reference mismatch.
* Retry where supported.

## 9. Update Documentation

Add the gateway to:

* Supported Gateways.
* Gateway Support Matrix.
* Gateway-specific documentation.
* Configuration documentation.

---

# Gateway Implementation Rules

When implementing a new gateway:

### Do

* Extend `BaseGateway`.
* Implement `PaymentGatewayInterface`.
* Keep API communication inside a gateway-specific API service.
* Keep webhook parsing inside a gateway-specific webhook handler.
* Reuse the existing repositories.
* Reuse `PaymentRequestDTO` where possible.
* Build `PaymentTransactionDTO` for persistence.
* Normalize gateway statuses.
* Store monetary values in minor units.
* Add automated tests.

### Do Not

* Duplicate `executePayment()`.
* Put gateway API calls inside repositories.
* Put gateway-specific API logic in `BaseGateway`.
* Modify generic payment logic for every new gateway.
* Store secrets directly in source code.
* Silently pretend unsupported operations are supported.

---

# Testing

Automated tests are not currently included in this package.

Testing coverage is planned for a future release and will include:

- Payment creation and processing.
- Stripe payment flows.
- Paymob payment flows.
- Refunds and partial refunds.
- Payment retries.
- Stripe webhook signature verification.
- Stripe webhook event handling.
- Paymob callback HMAC verification.
- Invalid and tampered webhook/callback payloads.
- Already-processed transactions.
- Unknown or invalid transactions.

Once the test suite is implemented, it will be integrated into the CI workflow to run automatically on pushes and pull requests.

---

## What Should Be Tested?

### Payment

Test:

* Successful payment.
* Failed payment.
* Invalid payment data.
* Customer creation/update.
* Transaction persistence.
* Gateway response mapping.
* Payment status mapping.

### Webhooks and Callbacks

Test:

* Valid signature.
* Invalid signature.
* Missing signature.
* Tampered payload.
* Unknown transaction.
* Already processed transaction.
* Supported events.
* Unsupported events.

For Stripe, test every event explicitly handled by the package.

For Paymob, test both valid and invalid HMAC callbacks.

### Refunds

Test:

* Full refund.
* Partial refund.
* Multiple partial refunds.
* Over-refund rejection.
* Unknown transaction.
* Gateway/local reference mismatch.
* Correct remaining amount.
* Correct final transaction status.

### Retry

Test:

* Valid retry.
* Retry of failed transaction.
* Retry of pending transaction where supported.
* Retry of an already completed transaction.
* Gateway API failure.

---


---

# Contributing

Contributions are welcome.

When adding or modifying functionality:

1. Follow the existing architecture.
2. Keep gateway-specific code inside its gateway directory.
3. Avoid changing the generic payment workflow unnecessarily.
4. Add or update tests.
5. Run the complete test suite.
6. Update the documentation.
7. Update the gateway support matrix when capabilities change.
8. Preserve backward compatibility.

---

# Project Structure

A simplified package structure:

``` text
lara_payments_ma/
├── LICENSE
├── README.md
├── composer.json
├── composer.lock
│
├── config/
│   ├── ma-drivers.php              # gateway driver registry
│   └── ma-payment.php              # main package config
│
├── database/
│   └── migrations/
│       ├── 2026_08_21_154005_create_payment_customers_table.php
│       ├── 2026_08_22_165124_create_payment_transactions_table.php
│       └── 2026_08_24_160438_create_refunded_payment_transactions_table.php
│
├── resources/
│   ├── js/
│   │   └── stripe/
│   │       └── MaPaymentStripe.js  # frontend Stripe integration JS
│   └── views/
│       ├── Stripe/
│       │   └── card.blade.php              # Stripe card payment view
│
└── src/
    ├── MaPaymentServiceProvider.php        # package service provider (bindings, migrations, views, lang, publishes)
    ├── PaymentGatewayManager.php           # resolves the active gateway driver
    │
    ├── DTOS/
    │   ├── PaymentRequestDTO.php           # incoming payment request data
    │   └── PaymentTransactionDTO.php       # transaction data transfer object
    │
    ├── Enums/
    │   └── PaymentStatus.php               # payment status enum
    │
    ├── Exceptions/
    │   ├── CustomerNotFoundException.php
    │   ├── GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException.php   # (typo: "Gatewat")
    │   ├── GatewayTxnIdAndLocalTxnIdNotSameException.php
    │   ├── MissingPaymentInfoException.php
    │   ├── RefundAmountGreaterThanTransactionAmountException.php
    │   ├── TransactionAlreadyProccessedException.php                # (typo: "Proccessed")
    │   ├── TransactionCannotProcessException.php
    │   ├── TransactionFailedException.php
    │   └── TransactionNotFoundException.php
    │
    ├── Facades/
    │   └── MaPayment.php                   # facade: MaPayment::gateway(...)
    │
    ├── Factories/
    │   └── PaymentGatewayFactory.php       # builds gateway instances
    │
    ├── Gateways/
    │   ├── BaseGateway.php                 # shared gateway logic
    │   ├── Paymob/
    │   │   ├── PaymobGateway.php
    │   │   └── Services/
    │   │       ├── PaymobApiService.php        # Paymob HTTP API calls
    │   │       └── PaymobWebhookHandler.php    # Paymob webhook verification/handling
    │   └── Stripe/
    │       ├── StripeGateway.php
    │       └── Services/
    │           ├── StripeApiService.php        # Stripe API calls
    │           └── StripeWebhookHandler.php    # Stripe webhook handling
    │
    ├── Interfaces/
    │   ├── PaymentGatewayInterface.php             # contract all gateways implement
    │   ├── TransactionRepositoryInterface.php      # transaction persistence contract
    │   └── ViewablePaymentGatewayInterface.php     # gateways that render their own view
    │
    ├── Models/
    │   ├── PaymentCustomer.php
    │   ├── PaymentTransaction.php
    │   └── RefundedPaymentTransaction.php
    │
    ├── Repositories/
    │   ├── PaymentCustomerRepository.php
    │   ├── RefundTransactionRepository.php
    │   └── TransactionRepository.php
    │
    ├── Services/
    │   ├── ClientApiService.php            # outbound API client helper
    │   ├── CustomerSerivce.php             # (typo in filename: "Serivce")
    │   └── PaymentTransaction.php          # transaction orchestration service
    │
    └── ValueObjects/
        ├── Money.php
        └── UserEmail.php
```

---

# Architecture Principles

The package follows several important principles:

### Single Responsibility

Different responsibilities are separated:

- Gateway
- API Service
- Webhook Handler
- Repository
- DTO
- Value Object

### Open/Closed Principle

A new gateway can be added without changing the generic payment workflow.

### Liskov Substitution

Gateway implementations follow the common gateway contract.

### Dependency Inversion

Infrastructure dependencies are injected through abstractions where appropriate.

### Separation of Concerns

Gateway-specific API behavior remains isolated from:

* Persistence.
* Generic payment orchestration.
* DTO definitions.
* Application integration.

---

## Author

[![Mohamed Allam](https://github.com/allamo123.png?size=90)](https://github.com/allamo123) 

## [License](https://github.com/allamo123/laravel-grapes/blob/main/LICENSE)

MIT © [Mohamed Allam ](https://github.com/allamo123)

