<?php

namespace Ma\Payment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ma\Payment\Gateways\Paymob\Services\PaymobSubscriptionService;
use Ma\Payment\Repositories\SubscriptionWebhookEventRepository;
use Throwable;

;

class UpdateSubscriptionJob implements ShouldQueue
{
    use Queueable;
    
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly array $data,
        private readonly string $eventId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        PaymobSubscriptionService $subscriptionService,
        SubscriptionWebhookEventRepository $eventRepository,
    ): void 
    {
        DB::transaction(function () use ($subscriptionService, $eventRepository) 
        {
            $event = $eventRepository->findById($this->eventId);

            $subscription = $this->data['subscription_data'];

            $subscriptionData = [
                'next_billing'    => $subscription['next_billing'],
                'starts_at'       => $subscription['starts_at'],
                'ends_at'         => $subscription['ends_at'],
                'reminder_date'   => $subscription['reminder_date'],
                'suspended_at'    => $subscription['suspended_at'],
                'resumed_at'      => $subscription['resumed_at'],
                'reactivated_at'  => $subscription['reactivated_at'],
                'status'          => $subscription['state'],
            ];

            $subscriptionService->updateLocalSubscription(
                $subscription['id'], 
                $subscriptionData
            );

            $eventRepository->markAsProcessed($event);
        });
    }

    public function failed(Throwable $exception): void
    {   
        $eventRepository = app(SubscriptionWebhookEventRepository::class);

        $event = $eventRepository->findById($this->eventId);

         $eventRepository->markAsFailed(
            $event,
            $exception?->getMessage() ?? 'Webhook processing failed.'
        );
    }
}
