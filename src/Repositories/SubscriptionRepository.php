<?php

namespace Ma\Payment\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Ma\Payment\Models\Subscription;

class SubscriptionRepository
{
    public function __construct(private Subscription $subscription)
    {}

    public function findByGatewayId(string $subscriptionGatewayId): Subscription
    {
        return $this->subscription->query()
            ->where('gateway_subscription_id', $subscriptionGatewayId)
            ->lockForUpdate()
            ->firstOrFail();
    }
    
    public function findById(string $id): Subscription
    {
        return $this->subscription->findOrFail($id);
    }

    public function paginateSubscrptions(string $gateway, int $perPage): LengthAwarePaginator
    {
        return $this->subscription->query()
            ->where('gateway', $gateway)
            ->paginate($perPage);
    }

    public function update(string $subscriptionGatewayId, array $data): Subscription
    {
        $subscription = $this->findByGatewayId($subscriptionGatewayId);
        $subscription->update($data);
        return $subscription;
    }

    public function create(array $data): Subscription
    {
        return $this->subscription->create($data);
    }
}