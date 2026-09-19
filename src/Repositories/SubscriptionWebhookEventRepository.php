<?php

namespace Ma\Payment\Repositories;

use Ma\Payment\Models\SubscriptionWebhookEvent;

class SubscriptionWebhookEventRepository
{
    public function __construct(
        private readonly SubscriptionWebhookEvent $event,
    ) {}

    public function findById(int $id): SubscriptionWebhookEvent
    {
        return $this->event->find($id);
    }

    public function findOrCreate(string $gateway, string $eventId, ?string $eventType, array $payload): SubscriptionWebhookEvent 
    {
        return $this->event->query()->firstOrCreate(
            [
                'gateway' => $gateway,
                'event_id' => $eventId,
            ],
            [
                'event_type' => $eventType,
                'payload' => json_encode($payload),
            ]
        );
    }

    public function markAsProcessed(SubscriptionWebhookEvent $event): SubscriptionWebhookEvent 
    {
        $event->update([
            'processed_at' => now(),
            'failure_reason' => null,
        ]);

        return $event;
    }

    public function markAsFailed(SubscriptionWebhookEvent $event, string $reason): SubscriptionWebhookEvent 
    {
        $event->update([
            'failure_reason' => $reason,
        ]);

        return $event;
    }
}