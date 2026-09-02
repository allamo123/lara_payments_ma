<?php

namespace Ma\Payment\Gateways\Stripe\Services;

use Illuminate\Support\Facades\Log;
use Ma\Payment\Enums\PaymentStatus;
use Stripe\Charge;
use Stripe\Event;
use Stripe\Refund;
use Stripe\Webhook;

class StripeWebhookHandler
{
    public function __construct() {}

    public function handle(string $payload, string $signature): array
    {
        $event = $this->verifySignature($payload, $signature);

        return match ($event->type) {
            'payment_intent.succeeded' => $this->paymentIntent($event, PaymentStatus::SUCCEEDED),

            'payment_intent.payment_failed' => $this->paymentIntent($event,PaymentStatus::FAILED),

            'payment_intent.canceled' => $this->paymentIntent($event, PaymentStatus::CANCELED),

            'refund.created' => $this->refundCreated($event),

            'charge.refunded' => $this->chargeRefunded($event),

            default => [
                'handled' => false,
                'event_type' => $event->type,
            ],
        };
    }

    private function verifySignature($payload, $signature): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('ma-payment.STRIPE_WEBHOOK_SECRET')
        );
    }

    private function paymentIntent(Event $event, PaymentStatus $status ): array 
    {
        return [
            'handled' => true,
            'event_type' => $event->type,
            'transaction_id' => $event->data->object->id,
            'status' => $status,
        ];
    }

    private function refundCreated(Event $event): array
    {
        /** @var Refund $refund */
        $refund = $event->data->object;

        return [
            'handled' => true,
            'event_type' => $event->type,

            // Parent transaction
            'transaction_id' => $refund->payment_intent,

            // Child refund
            'refund_id' => $refund->id,
            'refund_amount' => $refund->amount,
            'refund_currency' => $refund->currency,
            'refund_status' => $refund->status,
        ];
    }

    private function chargeRefunded(Event $event): array
    {
        /** @var Charge $charge */
        $charge = $event->data->object;

        Log::info('refund', ['charge_event' => print_r($charge, true)]);


        $status = match (true) {
            $charge->amount_refunded <= 0 => null,

            $charge->amount_refunded >= $charge->amount_captured => PaymentStatus::FULLY_REFUNDED,

            default => PaymentStatus::PARTIALLY_REFUNDED,
        };

        if ($status === null) {
            return [
                'handled' => false,
                'event_type' => $event->type,
            ];
        }

        return [
            'handled' => true,
            'event_type' => $event->type,

            // Parent transaction
            'transaction_id' => $charge->payment_intent,

            // Parent refund data
            'refund_id' => $charge->refunds->data[0]['id'],
            'status' => $status,
            'amount_captured' => $charge->amount_captured,
            'amount_refunded' => $charge->amount_refunded,
        ];
    }
}