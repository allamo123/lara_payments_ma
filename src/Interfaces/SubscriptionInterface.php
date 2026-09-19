<?php 

namespace Ma\Payment\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Ma\Payment\Models\Subscription;
use Ma\Payment\Models\SubscriptionPlan;

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