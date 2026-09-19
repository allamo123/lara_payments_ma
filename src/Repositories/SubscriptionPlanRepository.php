<?php

namespace Ma\Payment\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Ma\Payment\Models\SubscriptionPlan;

class SubscriptionPlanRepository
{
    public function __construct(private SubscriptionPlan $subscriptionPlan)
    {}

    public function findPlanByLocalId(int $id): SubscriptionPlan
    {
        return $this->subscriptionPlan->findOrFail($id);
    }

    public function findLocalPlanByGatewayId(string $gatewayPlanId): SubscriptionPlan
    {
        return $this->subscriptionPlan->query()
            ->where('gateway_plan_id', $gatewayPlanId)
            ->first();
    }

    public function findPlanByName(string $planName): SubscriptionPlan|null
    {
        return $this->subscriptionPlan->query()
            ->where('name', $planName)
            ->first();
    }

    public function create(array $data): void
    {
        $this->subscriptionPlan->create($data);
    }

    public function update(string $planId, array $data): void
    {
        $plan = $this->subscriptionPlan->query()
            ->where('gateway_plan_id', $planId)
            ->first();

        $plan->update($data);
    }

    public function listPlans(): Collection
    {
        return $this->subscriptionPlan->query()->get();   
    }
}