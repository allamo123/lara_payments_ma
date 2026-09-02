<?php

namespace Ma\Payment\Gateways\Paymob\Services;

use Ma\Payment\Services\ClientApiService;

class PaymobApiService
{
    private const BASE_API  = "https://accept.paymobsolutions.com/api";

    private readonly string $paymob_api_key;
    private readonly string $paymob_api_secret;
    private readonly string $paymob_integration_id;
    private readonly string $paymob_wallet_integration_id;
    private readonly string $paymob_iframe_id;
    private readonly string $paymob_currency;

    public function __construct(protected ClientApiService $clientApiService)
    {
        $this->paymob_api_key = config('ma-payment.PAYMOB_API_KEY');
        $this->paymob_api_secret = config('ma-payment.PAYMOB_API_SECRET');
        $this->paymob_integration_id = config('ma-payment.PAYMOB_INTEGRATION_ID');
        $this->paymob_iframe_id = config('ma-payment.PAYMOB_IFRAME_ID');
        $this->paymob_currency = config('ma-payment.PAYMOB_CURRENCY');
        $this->paymob_wallet_integration_id = config("ma-payment.PAYMOB_WALLET_INTEGRATION_ID");
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
}