<?php

namespace Ma\Payment\Gateways\Paymob;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Ma\Payment\DTOS\PaymentRequestDTO;
use Ma\Payment\DTOs\PaymentTransactionDTO;
use Ma\Payment\Enums\PaymentStatus;
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
use Ma\Payment\Gateways\Paymob\Services\PaymobWebhookHandler;
use Ma\Payment\Repositories\PaymentCustomerRepository;
use Ma\Payment\Services\CustomerSerivce;
use Ma\Payment\ValueObjects\Money;
use Ma\Payment\Repositories\RefundTransactionRepository;


class PaymobGateway extends BaseGateway implements PaymentGatewayInterface
{
    public function __construct(
        private PaymobApiService $paymobApiService,
        protected TransactionRepositoryInterface $transactionRepository,
        private PaymobWebhookHandler $paymobWebhookHandler,
        private PaymentCustomerRepository $customerRepository,
        protected CustomerSerivce $customerService,
        protected RefundTransactionRepository $refundTransactionRepository,
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

    protected function buildPaymentTransactionDTO(array $apiResponse, PaymentRequestDTO $paymentDto, int $package_customer_id): PaymentTransactionDTO
    {
        return new PaymentTransactionDTO(
            amount: $paymentDto->amount,
            customerId: $package_customer_id,
            source: $paymentDto->source,
            gatewayName: $this->gateway_name,
            orderId: (int) isset($apiResponse['order']) ? $apiResponse['order']['id'] : null,
            status: $this->mapStatus(strtolower($apiResponse['order']['payment_status']))->value,
            gatewayRefrence: null,
            currency: $apiResponse['order']['currency'],
            metadata: $apiResponse,
        );
    }
    
    public function verify(array|string $callbackResonse, ?string $signature = null): array
    {
        $this->paymobWebhookHandler->handle($callbackResonse);

        $transaction = $this->transactionRepository->getTransactionByOrderId($callbackResonse['order']);

        if (!$transaction) {
            throw new TransactionNotFoundException($callbackResonse['order']);
        }

        if ($this->mapStatus($transaction->status)->value !== 'pending') {
            throw new TransactionAlreadyProccessedException($callbackResonse['order']);
        }

        $isSuccess = filter_var($callbackResonse['success'], FILTER_VALIDATE_BOOLEAN);
       
        $txn_data = [
            'gateway_reference' => $callbackResonse['id'],
            'status' => $isSuccess ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED,
            'source_subtype' => strtolower($callbackResonse['source_data_sub_type']),
        ];

        $this->transactionRepository->updateByOrderId($callbackResonse['order'], $txn_data);

        return [
            'payment_id' => $callbackResonse['order'],
            'message' => $callbackResonse['data_message']
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
        )) {
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

        $refund_res  = $this->paymobApiService->refund($transaction->gateway_reference, new Money($amount)->toCents());

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
            'meta_data'         => json_encode($refund_data, JSON_PRETTY_PRINT)
        ];

        $remainingAmount = ((int) $transaction->remain_minor_amount - (int) new Money($amount)->toCents());

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
}
