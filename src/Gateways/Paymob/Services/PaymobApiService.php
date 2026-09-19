<?php

namespace Ma\Payment\Gateways\Paymob\Services;

use Illuminate\Support\Facades\Log;
use Ma\Payment\Services\ClientApiService;

class PaymobApiService
{
    private const BASE_API  = "https://accept.paymobsolutions.com/api";

    private readonly string $paymob_api_key;
    private readonly string $paymob_public_key;
    private readonly string $paymob_api_secret;
    private readonly string $paymob_integration_id;
    private readonly string $paymob_moto_integration_id;
    private readonly string $paymob_wallet_integration_id;
    private readonly string $paymob_iframe_id;
    private readonly string $paymob_currency;
    private readonly string $subscription_webhook_url;

    public function __construct(protected ClientApiService $clientApiService)
    {
        $this->paymob_api_key = config('ma-payment.PAYMOB_API_KEY');
        $this->paymob_public_key = config('ma-payment.PAYMOB_PUBLIC_KEY');
        $this->paymob_api_secret = config('ma-payment.PAYMOB_API_SECRET');
        $this->paymob_integration_id = config('ma-payment.PAYMOB_INTEGRATION_ID');
        $this->paymob_moto_integration_id = config('ma-payment.PAYMOB_MOTO_INTEGRATION_ID');
        $this->paymob_iframe_id = config('ma-payment.PAYMOB_IFRAME_ID');
        $this->paymob_currency = config('ma-payment.PAYMOB_CURRENCY');
        $this->paymob_wallet_integration_id = config("ma-payment.PAYMOB_WALLET_INTEGRATION_ID");
        $this->subscription_webhook_url = config("ma-payment.PAYMOB_SUBSCRIPTION_WEBHOOK_URL");
    }

    private function getAuthenticationToken(): string|array
    {
        $request_new_token = $this->clientApiService->post(self::BASE_API . '/auth/tokens', [
            "api_key" => $this->paymob_api_key
        ]);

        return $request_new_token;
    }

    public function getIframeUrl(float $amount, string $user_first_name, string $user_last_name, string $user_email, string $user_phone, string $source): string|array
    {
        $request_new_token = $this->getAuthenticationToken();

        $get_order =  $this->clientApiService->post(self::BASE_API . '/api/ecommerce/orders', [
            "auth_token" => $request_new_token['token'],
            "delivery_needed" => "false",
            "amount_cents" => $amount * 100,
            "items" => []
        ]);

        $get_url_token = $this->clientApiService->post(self::BASE_API . '/acceptance/payment_keys', [
            "auth_token" => $request_new_token['token'],
            "expiration" => 36000,
            "amount_cents" => $get_order['amount_cents'],
            "order_id" => $get_order['id'],
            "billing_data" => [
                "apartment" => "NA",
                "email" => $user_email,
                "floor" => "NA",
                "first_name" => $user_first_name,
                "street" => "NA",
                "building" => "NA",
                "phone_number" => $user_phone,
                "shipping_method" => "NA",
                "postal_code" => "NA",
                "city" => "NA",
                "country" => "NA",
                "last_name" => $user_last_name,
                "state" => "NA"
            ],
            "currency" => $this->paymob_currency,
            "integration_id" => $source === 'wallet' ? $this->paymob_wallet_integration_id : $this->paymob_integration_id
        ]);


        if ($source === 'wallet') {

                $get_pay_link = $this->clientApiService->post(self::BASE_API . '/acceptance/payments/pay', [
                        'source' => [
                            'identifier' => $user_phone,
                            'subtype'    => 'WALLET',
                        ],
                        'payment_token' => $get_url_token['token'],
                ]);

                return [
                  'paylink' =>  $get_pay_link['redirect_url'],
                  'order'   =>  $get_order,
                ];
        }

        $iframe_url = self::BASE_API . "/acceptance/iframes/" . $this->paymob_iframe_id . "?payment_token=" . $get_url_token['token'];

        return [
            'paylink' => $iframe_url,
            'order'   => $get_order,
        ];
    }

    public function getGatewayTransactionByOrderId(int $orderId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $get_transaction = $this->clientApiService->get(self::BASE_API . '/ecommerce/orders/' . $orderId, $request_new_token["token"]);

        return $get_transaction;
    }

    public function refund(int $transactionId, int $amount): array
    {
        $refund_response = $this->clientApiService->postWithSecretKey(self::BASE_API.'/acceptance/void_refund/refund', 
            [
                'transaction_id' => $transactionId,
                'amount_cents' => $amount
            ],
            $this->paymob_api_secret
        );

        return $refund_response;
    }

    public function createSubscriptionPlan(array $data): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $paymentPlan =  $this->clientApiService->post(self::BASE_API . '/acceptance/subscription-plans', [
            "auth_token" => $request_new_token['token'],
            "integration" => (int) $this->paymob_moto_integration_id,
            ...$data
        ]);

        return $paymentPlan;

    }

    public function updateSubscriptionPlan(string $planId, array $data): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $paymentPlan =  $this->clientApiService->put(self::BASE_API . "/acceptance/subscription-plans/$planId", [
            "auth_token" => $request_new_token['token'],
            "integration" => (int) $this->paymob_moto_integration_id,
            ...$data
        ]);

        return $paymentPlan;

    }

    public function ListSubscriptionPlans(): array
    {
        $request_new_token = $this->getAuthenticationToken();

        return $this->clientApiService->get(self::BASE_API. '/acceptance/subscription-plans', $request_new_token['token']);
    }

    public function susspendPlan(string $planId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        return $this->clientApiService->post(self::BASE_API. "/acceptance/subscription-plans/$planId/suspend", [
             "auth_token" => $request_new_token['token'],
        ]);
    }

    public function resumePlan(string $planId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        return $this->clientApiService->post(self::BASE_API. "/acceptance/subscription-plans/$planId/resume", [
             "auth_token" => $request_new_token['token'],
        ]);
    }

    public function subscribe(array $plan, array $customerData): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $customerName = explode(' ', $customerData['name']);

        $response = $this->clientApiService->postWithSecretKey("https://accept.paymob.com/v1/intention/", [
            "auth_token" => $request_new_token['token'],
            "currency" => $this->paymob_currency,
            "subscription_plan_id" => $plan['gateway_plan_id'],
            "amount" => $plan['minor_amount'],
            // 'subscriptionv2_id' => $subscriptionId,
            "payment_methods" => [
                (int) $this->paymob_moto_integration_id
            ],
            // 'special_reference' => $customerData['user_id'],
            'items' => [
                [
                    'name' => $plan['name'],
                    'amount' => $plan['minor_amount'],
                    'description' => $plan['name'].' subscription',
                    'quantity' => 1,
                ],
            ],
            'billing_data' => [
                'first_name' => $customerName[0],
                'last_name' => $customerName[count($customerName)-1],
                'phone_number' => $customerData['phone'],
                'email' => $customerData['email'],
            ],

        ], $this->paymob_api_secret);

        // dd($response);

       $secret = $response['client_secret'];

        return [
            'payLink' => "https://accept.paymob.com/unifiedcheckout/?publicKey=$this->paymob_public_key&clientSecret=$secret",
            'transacrion' => $response,
        ];
    }

    public function paginateGatewaySubscrptions(int $page): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $get_subscribers = $this->clientApiService->get(
            self::BASE_API . "/acceptance/subscriptions?page=$page", $request_new_token["token"]
        );

        return $get_subscribers;
    }

    public function cardTokens(int $subscriptionId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $card = $this->clientApiService->get(
            self::BASE_API . "/acceptance/subscriptions/$subscriptionId/card-tokens", $request_new_token["token"]
        );

        return $card;
    }

    public function getSubscriptionTransactions(string $subscriptionId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $subscription = $this->clientApiService->get(
            self::BASE_API . "/acceptance/subscriptions/$subscriptionId/transactions", $request_new_token["token"]
        );

        return $subscription;
    }

    public function findSubscription(int $subscriptionId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $subscription = $this->clientApiService->get(
            self::BASE_API . "/acceptance/subscriptions/$subscriptionId", $request_new_token["token"]
        );

        return $subscription;
    }

    public function findSubscriptionbyTransactionId(string $transactionId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        $subscription = $this->clientApiService->get(
            self::BASE_API . "/acceptance/subscriptions?transaction=$transactionId", $request_new_token["token"]
        );

        return $subscription;
    }

    public function registeSubscriptionWebhook(string $subsceriptionId): array
    {
        $request_new_token = $this->getAuthenticationToken();

         return $this->clientApiService->post(self::BASE_API. "/acceptance/subscriptions/$subsceriptionId/register_webhook", [
            "auth_token" => $request_new_token['token'],
            'url' => $this->subscription_webhook_url,
        ]);
    }

    public function suspendSubscription(string $subsceriptionId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        return $this->clientApiService->post(self::BASE_API. "/acceptance/subscriptions/$subsceriptionId/suspend", [
            "auth_token" => $request_new_token['token'],
        ]);
    }

    public function resumeSubscription(string $subsceriptionId): array
    {
        $request_new_token = $this->getAuthenticationToken();

        return $this->clientApiService->post(self::BASE_API. "/acceptance/subscriptions/$subsceriptionId/resume", [
            "auth_token" => $request_new_token['token'],
        ]);
    }

    public function updateSubscription(string $subsceriptionId, array $data): array
    {
         $request_new_token = $this->getAuthenticationToken();

        return $this->clientApiService->put(self::BASE_API. "/acceptance/subscriptions/$subsceriptionId", [
            "auth_token" => $request_new_token['token'],
            ...$data
        ]);
    }
}