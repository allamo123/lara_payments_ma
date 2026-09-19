<?php

namespace Ma\Payment\Gateways\Paymob\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Ma\Payment\DTOS\PaymentTransactionDTO;
use Ma\Payment\DTOS\PaymobSubscriptionPlanRequestDTO;
use Ma\Payment\DTOS\PaymobSubscriptionPlanResponseDTO;
use Ma\Payment\Enums\PaymentStatus;
use Ma\Payment\Models\Subscription;
use Ma\Payment\Models\SubscriptionPlan;
use Ma\Payment\Repositories\SubscriptionPlanRepository;
use Ma\Payment\Repositories\SubscriptionRepository;
use Ma\Payment\Repositories\TransactionRepository;
use Ma\Payment\Services\CustomerSerivce;
use Ma\Payment\ValueObjects\Money;
use Ma\Payment\ValueObjects\UserId;
use RuntimeException;

final class PaymobSubscriptionService
{
    public function __construct(
        private PaymobApiService $paymobApiService,
        private SubscriptionPlanRepository $subscriptionPlanRepository,
        private TransactionRepository $transactionRepository, 
        private SubscriptionRepository $subscriptionRepository, 
        private CustomerSerivce $customer,
    )
    {}

    public function createPlan(array $subscriptionPlan): array
    {
        $isPlanNameExist = $this->findPlanByName($subscriptionPlan['name']);

        if($isPlanNameExist)
            throw new RuntimeException('Cannot create plan with name "'.$subscriptionPlan['name'].'" because it is already exist'); 

        $subscriptionPlanRequestDTO = PaymobSubscriptionPlanRequestDTO::fromArray($subscriptionPlan);

        $response = $this->paymobApiService->createSubscriptionPlan(
            $subscriptionPlanRequestDTO->apiRequestData()
        );

        $subscriptionPlanResponseDTO = PaymobSubscriptionPlanResponseDTO::fromArray($response);

        $this->subscriptionPlanRepository->create(
            $subscriptionPlanResponseDTO->toDatabase($response)
        );

        return $response;
    }

    private function findPlanByName(string $planeName): SubscriptionPlan|null
    {
        return $this->subscriptionPlanRepository->findPlanByName($planeName);
    }

    public function findPlanByLocalId(int $id): SubscriptionPlan
    {
        return $this->subscriptionPlanRepository->findPlanByLocalId($id);
    }

    public function updateSubscriptionPlan(string $planId, array $modifiedData): array
    { 
        $requestData = [
            'amount_cents' => (new Money($modifiedData['amount']))->toCents(),
            'number_of_deductions' => $modifiedData['number_of_deductions'],
        ];

        $response = $this->paymobApiService->updateSubscriptionPlan(
            $planId,
            $requestData
        );

        $subscriptionPlanResponseDTO = PaymobSubscriptionPlanResponseDTO::fromArray($response);

        $this->subscriptionPlanRepository->update(
            $planId,
            $subscriptionPlanResponseDTO->toDatabase($response)
        );

        return $response;
    }

    public function listGatewayPlans(): array
    {
        return $this->paymobApiService->ListSubscriptionPlans();
    }

    public function listPlans(): Collection
    {
        return $this->subscriptionPlanRepository->listPlans();
    }

    public function suspendPlan(string $planId): array
    {
        $response = $this->paymobApiService->susspendPlan($planId);

        $subscriptionPlanResponseDTO = PaymobSubscriptionPlanResponseDTO::fromArray($response);

         $this->subscriptionPlanRepository->update(
            $planId,
            $subscriptionPlanResponseDTO->toDatabase($response)
        );

        return $response;
    }

    public function activatePlan(string $planId): array
    {
        $response = $this->paymobApiService->resumePlan($planId);

        $subscriptionPlanResponseDTO = PaymobSubscriptionPlanResponseDTO::fromArray($response);

         $this->subscriptionPlanRepository->update(
            $planId,
            $subscriptionPlanResponseDTO->toDatabase($response)
        );

        return $response;
    }

    public function subscribe(array $plan, array $customerData): array
    {
        // dd($plan);
        $customerData['gateway'] = 'paymob';
        
        $customer = $this->customer->getCustomerOrCreate($customerData);

        $response = $this->paymobApiService->subscribe($plan, $customerData);

        $transaction = $response['transacrion'];

        $paymentTransactionDTO = new PaymentTransactionDTO(
            gatewayName: 'paymob',
            customerId: new UserId($customer->id),
            orderId: $transaction['intention_order_id'],
            amount: new Money($transaction['intention_detail']['amount']/100),
            currency: $transaction['intention_detail']['currency'],
            source: 'card_subscription',
            status: PaymentStatus::PENDING->value,
            metadata: $transaction
        );

        $transaction = $this->transactionRepository->createOrUpdate(
           null, 
           $paymentTransactionDTO->toDatabase(),
        );

        return $response;
    }

    public function paginateGatewaySubscrptions(int $page): array
    {
        return $this->paymobApiService->paginateGatewaySubscrptions($page);
    }

    public function paginateLocalSubscrptions(int $perPage): LengthAwarePaginator
    {
        return $this->subscriptionRepository->paginateSubscrptions('paymob', $perPage);
    }

    public function findSubscrptionByLocalId(int $id): Subscription
    {
        return $this->subscriptionRepository->findById($id);
    }

    public function suspendSubscription(string $subscriptionId): array
    {
        return $this->paymobApiService->suspendSubscription($subscriptionId);
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        return $this->paymobApiService->resumeSubscription($subscriptionId);
    }

    public function updateLocalSubscription(string $subscrriptionId, array $data): void
    {
        $this->subscriptionRepository->update($subscrriptionId, $data);
    }

    public function updateGatewaySubscription(string $subscrriptionId, array $data): array
    {
        return $this->paymobApiService->updateSubscription($subscrriptionId, $data);
    }
}