<?php

namespace Ma\Payment\Gateways\Stripe;

use Ma\Payment\Gateways\BaseGateway;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ma\Payment\DTOS\PaymentRequestDTO;
use Ma\Payment\DTOs\PaymentTransactionDTO;
use Ma\Payment\Enums\PaymentStatus;
use Ma\Payment\Exceptions\GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException;
use Ma\Payment\Exceptions\RefundAmountGreaterThanTransactionAmountException;
use Ma\Payment\Exceptions\TransactionNotFoundException;
use Ma\Payment\Gateways\Stripe\Services\StripeApiService;
use Ma\Payment\Gateways\Stripe\Services\StripeWebhookHandler;
use Ma\Payment\Interfaces\PaymentGatewayInterface;
use Ma\Payment\Interfaces\TransactionRepositoryInterface;
use Ma\Payment\Repositories\PaymentCustomerRepository;
use Ma\Payment\Repositories\RefundTransactionRepository;
use Ma\Payment\Services\CustomerSerivce;
use Ma\Payment\ValueObjects\Money;

class StripeGateway extends BaseGateway implements PaymentGatewayInterface
{
    public function __construct(
        CustomerSerivce $customerService,
        TransactionRepositoryInterface $transactionRepository,
        private PaymentCustomerRepository $customerRepository,
        private StripeApiService $stripeService,
        private StripeWebhookHandler $stripeWebhookHandler,
        private RefundTransactionRepository $RefundTransactionRepository,
    )
    {
        parent::__construct($customerService, $transactionRepository);
        $this->gateway_name = 'stripe';
    }
    

    public function pay(array $data, bool $isRetry = false): array
    {
        try {
            $payment = $this->executePayment($data);

            return [
                'status' => $payment['status'],
                'minor_amount' => new Money($payment['amount'])->toCents(),
                'amount_received' => new Money($payment['amount_received'])->toPounds(),
            ];
        } catch (\Stripe\Exception\CardException $e) {

            $declinedPayment = $e->getError()->toArray();

            $paymentIntent = $declinedPayment['payment_intent'];

            $localCustomer = $this->customerService->getCustomerByUserId($data['customer']['id']);

            $paymentTransactionDTO = new PaymentTransactionDTO(
                amount: new Money($paymentIntent['amount']),
                customerId: $localCustomer->id,
                source: $data['source'],
                gatewayName: $this->gateway_name,
                status: $declinedPayment['code'],
                currency: $paymentIntent['currency'],
                metadata: $declinedPayment,
                gatewayRefrence: $paymentIntent['id'],
                source_subtype: strtolower($declinedPayment['payment_method']['card']['brand']),
            );

            $this->transactionRepository->createOrUpdate(
                null,
                $paymentTransactionDTO->toDatabase()
            );

            throw $e;
        }
    }

    protected function ensureGatewayCustomer(?string $gatewayCustomerId, PaymentRequestDTO $paymentDto): string
    {
        if ($gatewayCustomerId !== null) {
            $paymentDto->attachGatewayCustomerId($gatewayCustomerId);
            return $gatewayCustomerId;
        }
        
        $customerData = $paymentDto->customerApi();

        unset($customerData['id']);

        $customer = $this->stripeService->createCustomer($customerData);
        $paymentDto->attachGatewayCustomerId($customer->id);
        return $customer->id;
    }

    protected function sendPaymentRequest(PaymentRequestDTO $paymentDto): array
    {
        return $this->stripeService->createPaymentIntent($paymentDto->paymentData());
    }

    protected function buildPaymentTransactionDTO(array $apiResponse, PaymentRequestDTO $paymentDto, int $package_customer_id): PaymentTransactionDTO
    {
        return new PaymentTransactionDTO(
            amount: $paymentDto->amount,
            customerId: $package_customer_id,
            source: $paymentDto->source,
            gatewayName: $this->gateway_name,
            gatewayRefrence: $apiResponse['id'],
            orderId: null,
            status: $this->mapStatus(strtolower($apiResponse['status']))->value,
            currency: $apiResponse['currency'],
            source_subtype: $paymentDto->payment_method['card']['brand'],
            metadata: $apiResponse,
        );
    }

    public function verify(array|string $callbackResponse, ?string $signature = null): array
    {

        if (!is_string($callbackResponse)) {
            throw new \InvalidArgumentException(
                'Stripe webhook payload must be a string.'
            );
        }

        if ($signature === null) {
            throw new \InvalidArgumentException(
                'Stripe webhook signature is required.'
            );
        }

        $event = $this->stripeWebhookHandler->handle($callbackResponse, $signature);

        if (!$event['handled']) {
            return $event;
        }

        //  Log::info('refund', ['whole_event' => print_r($event, true)]);

        if(!$event['handled']){
            Log::info('event', [
                'event_type' => $event['event_type']
            ]);

            return $event;
        }

        return DB::transaction(function () use ($event) {

            $transaction = $this->transactionRepository->getTransactionByRef($event['transaction_id']);

            if (!$transaction) {
                throw new TransactionNotFoundException(
                    $event['transaction_id']
                );
            }

            /*
             * refund.created
             *
             * Create the individual refund child record.
             */
            if ($event['event_type'] === 'refund.created') {

                $this->RefundTransactionRepository->createRefundTransaction([
                    'parent_transaction' => $event['transaction_id'],
                    'transaction_id'     => $event['refund_id'],
                    'minor_amount'       => $event['refund_amount'],
                    'currency'           => $event['refund_currency'],
                    'status'             => $this->mapStatus($event['refund_status'])->value,
                    'meta_data'          => json_encode($event, JSON_PRETTY_PRINT),
                ]);

                // Log::info('refund', ['whole_event' => print_r($event, true)]);


                return [
                    'handled' => true,
                    'event_type' => $event['event_type'],
                    'transaction_id' => $transaction->gateway_reference,
                ];
            }

            /*
             * charge.refunded
             *
             * Update the parent transaction's
             * refund state.
             */
            $transactionData = [
                'status' => $event['status'],
            ];

            if (in_array($event['status'], [
                PaymentStatus::PARTIALLY_REFUNDED,
                PaymentStatus::FULLY_REFUNDED,
            ], true)) 
            {
                $transactionData['remain_minor_amount'] = ($event['amount_captured'] - $event['amount_refunded']);
            }
                
            $transaction->update($transactionData);

            // Log::info('refund', ['whole_event' => print_r($event, true)]);

            if (isset($event['refund_id'])) {
                Log::info('refund', ['event_id' => 'exist']);
                
                $this->RefundTransactionRepository->updateRefundTransaction($event['refund_id'], [
                    'refund_type' => $event['status']->value,
                ]);
            }

            return $transaction->toArray();
        });
    }

    public function getTransactions(?string $status = null): Collection
    {
        return $this->transactionRepository->getAll($status);
    }

    public function getCustomerTransactions(int $userId, ?string $status = null): Collection
    {
        return $this->customerRepository->getCustomertTransactions($userId, $status);
    }

    # need to implement
    public function getGatewayTransactionByOrderId(int $gatewayOrderId)
    {
        // return $this->paymobApiService->getGatewayTransactionByOrderId($gatewayOrderId);
    }

    public function retryPayment(int $localTransactionId, ?string $paymentMethodId = null): array
    {
        $transaction = $this->transactionRepository->findByLocaleId($localTransactionId);
        $gatewayRef  = $transaction->gateway_reference;
        $payment = $this->stripeService->retryPayment($gatewayRef, $paymentMethodId);

        return [
            'status' => $payment['status'],
            'minor_amount' => new Money($payment['amount'])->toCents(),
            'amount_received' => new Money($payment['amount_received'])->toPounds(),
        ];
    }

    public function refund(string $id, int $amount): void
    {
       $transaction = $this->transactionRepository->findByLocaleId($id);

       if (!$transaction) {
            throw new TransactionNotFoundException($id);
       }
       
       if ($transaction->remain_minor_amount < $amount) {
           throw new RefundAmountGreaterThanTransactionAmountException($transaction->id, $amount, $transaction->minor_amount);
        }

       $payment = $this->stripeService->refundPayment($transaction->gateway_reference, new Money($amount)->toCents());

        if ((string) $payment['payment_intent'] !== (string) $transaction->gateway_reference) {
            throw new GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException($transaction->id, $transaction->gateway_reference, $this->gateway_name);
        }
    }
}
