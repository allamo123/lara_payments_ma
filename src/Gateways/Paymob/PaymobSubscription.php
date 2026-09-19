<?php

namespace Ma\Payment\Gateways\Paymob;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Ma\Payment\Gateways\Paymob\Handlers\PaymobSubscriptionWebhookHandler;
use Ma\Payment\Gateways\Paymob\Services\PaymobSubscriptionService;
use Ma\Payment\Interfaces\SubscriptionInterface;
use Ma\Payment\Models\Subscription;
use Ma\Payment\Models\SubscriptionPlan;

final class PaymobSubscription implements SubscriptionInterface
{
    public function __construct(
        private PaymobSubscriptionService $subscriptionService,
        private PaymobSubscriptionWebhookHandler $subscriptionWebhookHandler,
    )
    {}

    public function createPlan(array $subscriptionPlan): array
    {
        $result = $this->subscriptionService->createPlan($subscriptionPlan);
        return $result;
    }

    public function findPlanByLocalId(int $id): SubscriptionPlan
    {
        $result = $this->subscriptionService->findPlanByLocalId($id);
        return $result;
    }

    public function updateSubscriptionPlan(string $planId, array $subscriptionPlan): array
    {
        $result = $this->subscriptionService->updateSubscriptionPlan($planId, $subscriptionPlan);
        return $result;
    }

    public function listPlans(): Collection
    {
        return $this->subscriptionService->listPlans();
    }

    public function suspendPlan(string $planId): array
    {
        return $this->subscriptionService->suspendPlan($planId);
    }

    public function resumePlan(string $planId): array
    {
        return $this->subscriptionService->activatePlan($planId);
    }

    public function subscribe(array $plan, array $customerData): array
    {
        return $this->subscriptionService->subscribe($plan, $customerData);
    }

    public function paginateLocalSubscrptions(?int $perPage = 8): LengthAwarePaginator
    {
        return $this->subscriptionService->paginateLocalSubscrptions($perPage);
    }

    public function findSubscrptionByLocalId(int $id): Subscription
    {
        return $this->subscriptionService->findSubscrptionByLocalId($id);
    }

    public function suspendSubscription(string $subscriptionId): array
    {
        return $this->subscriptionService->suspendSubscription($subscriptionId);
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        return $this->subscriptionService->resumeSubscription($subscriptionId);
    }

    public function lifeCycle(array $subscriptionData): void
    {
        $this->subscriptionWebhookHandler->handle($subscriptionData);
    }

    public function updateGatewaySubscription(string $subscriptionGatewayId, array $subscriptionData): array
    {
        return $this->subscriptionService->updateGatewaySubscription($subscriptionGatewayId, $subscriptionData);
    }
}