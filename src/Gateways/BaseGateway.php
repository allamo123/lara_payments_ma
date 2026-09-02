<?php

namespace Ma\Payment\Gateways;

use Ma\Payment\DTOS\PaymentRequestDTO;
use Ma\Payment\DTOs\PaymentTransactionDTO;
use Ma\Payment\Enums\PaymentStatus;
use Ma\Payment\Interfaces\TransactionRepositoryInterface;
use Ma\Payment\Services\CustomerSerivce;

abstract class BaseGateway 
{
	protected string $gateway_name;

	public function __construct(
			protected CustomerSerivce $customerService,
			protected TransactionRepositoryInterface $transactionRepository,
	){}
	
	final protected function executePayment(array $data, bool $isRetry = false) : array
	{
		$data['gateway'] = $this->gateway_name;
		
		$paymentDto = PaymentRequestDTO::fromArray($data);

        $customer = $this->customerService->getCustomerOrCreate($paymentDto->customer());
		
		$gatewayCustomerId  = $this->ensureGatewayCustomer($customer->gateway_customer_id, $paymentDto);
		
		if ($gatewayCustomerId !== $customer->gateway_customer_id) {
			$customer = $this->customerService->updateCustomer($customer->user_id,
            [
                'gateway_customer_id' => $paymentDto->gateway_customer_id,
				'gateway' => $this->gateway_name,
            ], $this->gateway_name);
    	}
		
		$response = $this->sendPaymentRequest($paymentDto);
		
		$paymentTransactionDTO = $this->buildPaymentTransactionDTO($response, $paymentDto, $customer->id);
		
		$this->transactionRepository->createOrUpdate(
            $isRetry ? $data['id'] : null,
            $paymentTransactionDTO->toDatabase()
        );
	
		return $response;
	}

	protected function ensureGatewayCustomer(?string $gatewayCustomerId, PaymentRequestDTO $paymentDto): ?string 
	{
		$paymentDto->attachGatewayCustomerId(null);
		return null;
	}

	abstract protected function sendPaymentRequest(PaymentRequestDTO $paymentDto): array;

	abstract protected function buildPaymentTransactionDTO(array $apiResponse, PaymentRequestDTO $paymentDto, int $package_customer_id): PaymentTransactionDTO;

	protected function mapStatus(string $status): PaymentStatus
	{
		return match ($status) {
			'approved', 'succeeded', 'paid', 'success' => PaymentStatus::SUCCEEDED,
			'pending', 'processing', 'unpaid' => PaymentStatus::PENDING,
			'failed', 'declined' => PaymentStatus::FAILED,
			'canceled' => PaymentStatus::CANCELED,
			'fully_refunded', 'refunded' => PaymentStatus::FULLY_REFUNDED,
     		'partially_refunded' => PaymentStatus::PARTIALLY_REFUNDED,
			default => PaymentStatus::FAILED,
		};
	}
}