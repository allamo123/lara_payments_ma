<?php

namespace Ma\Payment\Gateways\Paymob;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ma\Payment\DTOS\PaymentRequestDTO;
use Ma\Payment\DTOS\PaymentTransactionDTO;
use Ma\Payment\Enums\PaymentStatus;
use Ma\Payment\Enums\SubscriptionStatus;
use Ma\Payment\Enums\SubscriptionTransactionType;
use Ma\Payment\Interfaces\PaymentGatewayInterface;
use Ma\Payment\Interfaces\TransactionRepositoryInterface;
use Ma\Payment\Gateways\BaseGateway;
use Ma\Payment\Gateways\Paymob\Services\PaymobApiService;
use Ma\Payment\Exceptions\GatewayTxnIdAndLocalTxnIdNotSameException;
use Ma\Payment\Exceptions\GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException;
use Ma\Payment\Exceptions\RefundAmountGreaterThanTransactionAmountException;
use Ma\Payment\Exceptions\TransactionAlreadyProccessedException;
use Ma\Payment\Exceptions\TransactionNotFoundException;
use Ma\Payment\Exceptions\TransactionCannotProcessException;
use Ma\Payment\Gateways\Paymob\Handlers\PaymobSubscriptionCallbackHandler;
use Ma\Payment\Gateways\Paymob\Handlers\PaymobTransactionCallbackHandler;
use Ma\Payment\Interfaces\SubscriptionInterface;
use Ma\Payment\Interfaces\SubscrptionableInterface;
use Ma\Payment\Repositories\PaymentCustomerRepository;
use Ma\Payment\Services\CustomerSerivce;
use Ma\Payment\ValueObjects\Money;
use Ma\Payment\Repositories\RefundTransactionRepository;
use Ma\Payment\Repositories\SubscriptionPlanRepository;
use RuntimeException;

class PaymobGateway extends BaseGateway implements PaymentGatewayInterface, SubscrptionableInterface
{
    public function __construct(
        private PaymobApiService $paymobApiService,
        protected TransactionRepositoryInterface $transactionRepository,
        private PaymobTransactionCallbackHandler $paymobCallbackHandler,
        private PaymobSubscriptionCallbackHandler $paymobSubscriptionCallbackHandler,
        private PaymentCustomerRepository $customerRepository,
        protected CustomerSerivce $customerService,
        private RefundTransactionRepository $refundTransactionRepository,
        private SubscriptionPlanRepository $subscriptionPlan,
        private PaymobSubscription $paymobSubscription,
    ) {
        $this->gateway_name = 'paymob';
        parent::__construct($customerService, $transactionRepository);
    }

    public function pay(array $data, bool $isRetry = false): string
    {
        $this->gateway_name = 'paymob';
        $payment = $this->executePayment($data, $isRetry);

        return $payment['paylink'];
    }

    protected function sendPaymentRequest(PaymentRequestDTO $paymentDto): array
    {
        $response =  $this->paymobApiService->getIframeUrl(
            $paymentDto->amount->value(),
            $paymentDto->user_first_name,
            $paymentDto->user_last_name,
            $paymentDto->user_email->value(),
            $paymentDto->user_phone,
            $paymentDto->source
        );

        return $response;    
    }

    protected function buildPaymentTransactionDTO(array $apiResponse, $paymentDto = null): PaymentTransactionDTO
    {
        $apiResponse['orderId'] = $apiResponse['order']['id'];
        $apiResponse['payment_status'] = $this->mapStatus(strtolower($apiResponse['order']['payment_status']))->value;

        return PaymentTransactionDTO::fromArray($apiResponse);
    }
    
    public function verify(array|string $processedTransaction, ?string $signature = null): array
    {
        if (is_string($processedTransaction)) {
            $processedTransaction = json_decode($processedTransaction, true, 512, JSON_THROW_ON_ERROR);
        }

        $webhook = isset($processedTransaction['intention']) 
        ? $this->paymobSubscriptionCallbackHandler->handle($processedTransaction)
        : $this->paymobCallbackHandler->handle($processedTransaction);

        if ($webhook['type'] === 'TOKEN') {

           $card = [
                'gateway'         => 'paymob',
                'gateway_card_id' => $webhook['data']['id'],
                'token'           => $webhook['data']['token'],
                'email'           => $webhook['data']['email'],
                'brand'           => $webhook['data']['card_subtype'],
                'last_four'       => substr($webhook['data']['masked_pan'], -4),
                'expiry_month'    => $webhook['data']['expiry_month'],
                'expiry_year'     => $webhook['data']['expiry_year'],
                'cardholder_name' => $webhook['data']['cardholder_name'],
                'metadata'        => json_encode($webhook['data']),
            ];

            $this->customerService->SaveNewCard($card);

            return $card;
        }

        $ProccessedTransaction = $webhook['data']['transaction']['obj'];

        $orderId = $ProccessedTransaction['order']['id'];

        $transaction = $this->transactionRepository->getTransactionByOrderId($orderId);

        $subscription = null;

        #Verify & Trust subscription by callback
        if ($webhook['source'] === 'subscription') {

            foreach ([2, 4, 6, 8] as $delay) {

                sleep($delay);

                $subscription = $this->paymobApiService->findSubscriptionbyTransactionId($ProccessedTransaction['id']);
               
                if (!empty($subscription['results'])) {
                    break;
                }

            }

            if (empty($subscription['results'])) {
                throw new RuntimeException('Subscription not found at Paymob');
            }

            if(!empty($subscription['results']))
                $subscription = $subscription['results'][0];

            if ((int) $subscription['initial_transaction'] !== (int) $ProccessedTransaction['id']) 
            {
                throw new RuntimeException(
                    'Subscription does not belong to the callback transaction.'
                );
            }
        }
        #End of Verification & Trusting subscription by callback

        if (!$transaction) {
            throw new TransactionNotFoundException($orderId);
        }

        if ($this->mapStatus($transaction->status)->value !== 'pending') {
            throw new TransactionAlreadyProccessedException($orderId);
        }

        DB::transaction(function () use ($ProccessedTransaction, $subscription, $transaction) {

            $isSuccess = filter_var($ProccessedTransaction['success'], FILTER_VALIDATE_BOOLEAN);
       
            $txn_data = [
                'gateway_reference' => $ProccessedTransaction['id'],
                'status' => $isSuccess ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED,
                'source_subtype' => strtolower($ProccessedTransaction['source_data']['sub_type']),
            ];

            if ($subscription) {

                $localPlan = $this->subscriptionPlan->findLocalPlanByGatewayId($subscription['plan_id']);

                $subscriptionData = [
                    'gateway' => $transaction->gateway,
                    'gateway_subscription_id' => $subscription['id'],
                    'customer_id' => $transaction->customer_id,
                    'plan_id' => $localPlan->id,
                    'transaction_id' => $subscription['initial_transaction'],
                    'next_billing' => $subscription['next_billing'],
                    'starts_at' => $subscription['starts_at'],
                    'ends_at' => $subscription['ends_at'],
                    'reminder_date' => $subscription['reminder_date'],
                    'status' => SubscriptionStatus::ACTIVE->value,
                ];

                $subscription = $this->customerService->subscribe(
                    $subscription['client_info']['email'], 
                    $subscriptionData
                );

                $this->paymobApiService->registeSubscriptionWebhook($subscription->gateway_subscription_id);

                $txn_data['subscription_id'] = $subscription->id;
                $txn_data['subscription_transaction_type'] = SubscriptionTransactionType::INITIAL->value;
            }


            $this->transactionRepository->updateByOrderId(
                $ProccessedTransaction['order']['id'], $txn_data
            );
        });

        return [
            'order' => $ProccessedTransaction['order'],
        ];
    }

    public function getTransactions(?string $status = null): Collection
    {
        return $this->transactionRepository->getAll($status);
    }

    public function getCustomerTransactions(int $userId, ?string $status = null): Collection
    {
        return $this->customerRepository->getCustomertTransactions($userId, $status);
    }

    public function getGatewayTransactionByOrderId(int $gatewayOrderId)
    {
        return $this->paymobApiService->getGatewayTransactionByOrderId($gatewayOrderId);
    }
    
    public function retryPayment(int $id, ?string $param = null): string
    {
        $transaction = $this->transactionRepository->findByLocaleId($id);

        $orderId = $transaction->order_id;

        if (!$transaction) {
            throw new TransactionNotFoundException($orderId);
        }

        if (! in_array(
                $transaction->status,
                [
                    PaymentStatus::FAILED->value,
                    PaymentStatus::PENDING->value,
                ],
                true
        ) && $transaction->source !== 'card_subscription') {
            throw new TransactionCannotProcessException($transaction->order_id);
        }
        
        $customerName = explode(' ', $transaction->customer->name);
        
        $payLink = $this->pay([
            'id' => $transaction->id,
            'amount' => $transaction->minor_amount / 100,
            'currency' => $transaction->currency,
            'customer' => [
                'id' => $transaction->customer->user_id,
                'first_name' => $customerName[0],
                'last_name' => $customerName[1],
                'email' => $transaction->customer->email,
                'phone' => $transaction->customer->phone,
            ],
            'source' => $transaction->source,
        ], true);

        return $payLink;
    }

    public function refund(string $transactionId, int $amount): void
    {
        $transaction = $this->transactionRepository->findByLocaleId($transactionId);

        if (!$transaction) {
            throw new TransactionNotFoundException($transactionId);
        }

        if ($transaction->remain_minor_amount < $amount) {
           throw new RefundAmountGreaterThanTransactionAmountException($transaction->id, $amount, $transaction->minor_amount);
        }

        $refund_res  = $this->paymobApiService->refund($transaction->gateway_reference, (new Money($amount))->toCents());

        if ((int) $refund_res['order']['id'] !== (int) $transaction->order_id) {
            throw new GatewatTxnOrderIdAndLocalTxnOrderIdNotSameException($transaction->id, $transaction->gateway_reference, $this->gateway_name);
        }

        if ((int) $refund_res['parent_transaction'] !== (int) $transaction->gateway_reference) {
            throw new GatewayTxnIdAndLocalTxnIdNotSameException($transaction->gateway_reference, $this->gateway_name);
        }

        $refund_data = $refund_res['data'];

        $refund_type = $this->mapStatus(strtolower($refund_data['migs_order']['status']))->value;

        $refundTxnData = [
            'parent_transaction'         => $transaction->gateway_reference,
            'order_id'          => $refund_res['order']['id'],
            'transaction_id'    => $refund_data['migs_transaction']['id'],
            'minor_amount'      => $refund_res['amount_cents'],
            'refund_type'       => $refund_type,
            'currency'          => $refund_res['currency'],
            'status'            => $this->mapStatus(strtolower($refund_data['migs_result']))->value,
            'meta_data'         => json_encode($refund_data)
        ];

        $remainingAmount = $this->calculateRemainMinorAmount(
            (int) $transaction->remain_minor_amount, 
            (int) (new Money($amount))->toCents()
        );

        $updatedTxnData = [
            'status' => !$remainingAmount ? PaymentStatus::FULLY_REFUNDED : $refundTxnData['refund_type'],
            'remain_minor_amount' => $remainingAmount,
        ];
        
        $orderId = $transaction->order_id;

        DB::transaction(function () use ($orderId, $refundTxnData, $updatedTxnData) {
            $this->transactionRepository->updateByOrderId($orderId, $updatedTxnData);
            $this->refundTransactionRepository->createRefundTransaction($refundTxnData);
        });

    }

    public function subscription(): SubscriptionInterface
    {
        return $this->paymobSubscription;
    }
}
