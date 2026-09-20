# 6. Gateway Architecture

[← Documentation index](README.md)

This chapter describes the **public** architecture only — what your application talks
to. Internal services and repositories are documented in
[9. Advanced / Developer Documentation](09-advanced.md).

---

## Resolving a gateway

```text
Application
     │
     │  MaPayment::driver('paymob')
     ▼
Ma\Payment\Facades\MaPayment
     │
     ▼
Ma\Payment\PaymentGatewayManager
     │
     ▼
Ma\Payment\Factories\PaymentGatewayFactory
     │   (reads config('ma-drivers'))
     ▼
Gateway instance (StripeGateway | PaymobGateway | your gateway)
```

```php
use Ma\Payment\Facades\MaPayment;

$payment = MaPayment::driver('paymob');
```

* The driver registry is `config('ma-drivers')`, merged from the package
  [`config/ma_payment_drivers.php`](../config/ma_payment_drivers.php).
* Unknown drivers throw `Exception: Payment gateway [<driver>] does not exist`.
* Registered classes must implement
  `Ma\Payment\Interfaces\PaymentGatewayInterface`, otherwise the factory throws
  `Exception: [<class>] must implement PaymentGatewayInterface.`

---

## The payment contract

Every gateway implements:

```php
interface PaymentGatewayInterface
{
    public function pay(array $data, bool $isRetry): string|array;
    public function verify(array|string $data, ?string $param = null): array;
    public function getTransactions(?string $status): Collection;
    public function getCustomerTransactions(int $id, ?string $status): Collection;
    public function getGatewayTransactionByOrderId(int $orderId);
    public function retryPayment(int $id, ?string $param): string|array;
    public function refund(string $localTransactionId, int $amount): void;
}
```

This is the contract your application codes against. See
[4. Payments](04-payments.md) for behaviour and per-gateway differences.

---

## The subscription capability

Subscription support is an **optional capability layered on top of a gateway**, not a
separate gateway:

```php
interface SubscrptionableInterface          // note: this is the exact class name in the package
{
    public function subscription(): SubscriptionInterface;
}
```

`PaymobGateway` implements both `PaymentGatewayInterface` and
`SubscrptionableInterface`:

```php
class PaymobGateway extends BaseGateway
    implements PaymentGatewayInterface, SubscrptionableInterface
```

Your application therefore reaches subscriptions like this:

```php
use Ma\Payment\Facades\MaPayment;

$payment = MaPayment::driver('paymob');

$payment->subscription()->createPlan([...]);
$payment->subscription()->subscribe($plan, $customerData);
```

The `subscription()` object is a `PaymobSubscription` instance that implements:

```php
interface SubscriptionInterface
{
    public function createPlan(array $data): array;
    public function findPlanByLocalId(int $id): SubscriptionPlan;
    public function updateSubscriptionPlan(string $planId, array $data): array;
    public function listPlans(): Collection;
    public function suspendPlan(string $planId): array;
    public function resumePlan(string $planId): array;
    public function subscribe(array $plan, array $customerData): array;
    public function paginateLocalSubscrptions(?int $perPage = 8): LengthAwarePaginator;
    public function findSubscrptionByLocalId(int $id): Subscription;
    public function suspendSubscription(string $subscriptionId): array;
    public function resumeSubscription(string $subscriptionId): array;
    public function lifeCycle(array $subscriptionData): void;
    public function updateGatewaySubscription(string $subscriptionGatewayId, array $subscriptionData): array;
}
```

Behaviour, arguments, and returned data are documented in
[5. Subscriptions](05-subscriptions.md).

---

## Gateway support for subscriptions

| Gateway | `subscription()` available | Notes                                            |
| ------- | :------------------------: | ------------------------------------------------ |
| Paymob  |             ✅             | Full subscription API (plans, subscriptions, callbacks). |
| Stripe  |             ❌             | `StripeGateway` implements `PaymentGatewayInterface` only, so `subscription()` does not exist on it. |

Always resolve subscriptions from the Paymob driver:

```php
$payment->subscription();   // ✅ Paymob
```

```php
MaPayment::driver('stripe')->subscription();   // ❌ method not defined on the Stripe gateway
```

---

## What your application should and should not touch

**Use:**

* `MaPayment::driver(...)`
* the gateway contract methods (`pay`, `verify`, `refund`, `retryPayment`,
  `getTransactions`, `getCustomerTransactions`, `getGatewayTransactionByOrderId`)
* `$payment->subscription()` and its methods
* the public models: `PaymentCustomer`, `PaymentTransaction`, `RefundedPaymentTransaction`,
  `CustomerCard`, `SubscriptionPlan`, `Subscription`, `SubscriptionWebhookEvent`
* the public enums: `PaymentStatus`, `SubscriptionStatus`, `SubscriptionTransactionType`
* the public value objects: `Money`, `UserEmail`, `UserId`

**Do not depend on:**

* gateway-internal services such as `PaymobSubscriptionService` or `PaymobApiService`
* gateway-internal callback handlers
* internal repositories (`SubscriptionPlanRepository`, `SubscriptionRepository`,
  `SubscriptionWebhookEventRepository`, `TransactionRepository`,
  `PaymentCustomerRepository`, `RefundTransactionRepository`)

Those classes are wiring details and may change between minor releases. They are
described in [9. Advanced / Developer Documentation](09-advanced.md) only to help you
extend or debug the package.

---

## Extension points

* **Adding a new payment gateway** — implement `PaymentGatewayInterface`, extend
  `BaseGateway`, and register the driver. Step-by-step instructions:
  [9. Advanced → Extending gateways](09-advanced.md#extending-gateways).
* **Adding another subscription implementation** — implement
  `SubscriptionInterface` and expose it through `SubscrptionableInterface::subscription()`:
  [9. Advanced → Adding another subscription implementation](09-advanced.md#adding-another-subscription-implementation).

---

[← Previous: 5. Subscriptions](05-subscriptions.md) · [Next: 7. Configuration →](07-configuration.md)

