<?php 

namespace Ma\Payment\Gateways\Paymob\Handlers;

use Illuminate\Support\Facades\Log;
use Ma\Payment\Jobs\UpdateSubscriptionJob;
use Ma\Payment\Models\SubscriptionWebhookEvent;
use Ma\Payment\Repositories\SubscriptionWebhookEventRepository;

final class PaymobSubscriptionWebhookHandler
{
    public function __construct(
        private SubscriptionWebhookEventRepository $eventRepository
    )
    {}

    public function handle(array $payload)
    {
        $triggerType = $payload['trigger_type'];

        match ($triggerType) {
            'resumed'   => $this->handleUpdateSubscription($payload),
            'suspended' => $this->handleUpdateSubscription($payload),
            'cancelled' => $this->handleUpdateSubscription($payload),
            'updated' => $this->handleUpdateSubscription($payload),
            default     => Log::info('extra triger types are not defined', [
                'triggers_type' => $triggerType,
                'data'          => $payload,
            ]),
        };

        Log::info('processed subscription update', [
            'data' => $payload
        ]);
    }

    public function handleUpdateSubscription(array $payload)
    {
        $event = $this->createEvent($payload);

        if ($event->processed_at !== null) {
            return null;
        }

        UpdateSubscriptionJob::dispatch($payload, $event->id);
    }



    private function createEvent(array $payload): SubscriptionWebhookEvent
    {
        return $this->eventRepository->findOrCreate(
            gateway: 'paymob',
            eventId: $payload['paymob_request_id'],
            eventType: $payload['trigger_type'] ?? null,
            payload:  $payload,
        );
    }
}