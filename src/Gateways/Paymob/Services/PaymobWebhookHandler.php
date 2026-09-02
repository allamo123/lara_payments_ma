<?php

namespace Ma\Payment\Gateways\Paymob\Services;

use RuntimeException;

final class PaymobWebhookHandler
{
    public function handle(array $callbackResponse)
    {
        $string = 
            $callbackResponse['amount_cents'] . 
            $callbackResponse['created_at'] . 
            $callbackResponse['currency'] . 
            $callbackResponse['error_occured'] . 
            $callbackResponse['has_parent_transaction'] . 
            $callbackResponse['id'] . 
            $callbackResponse['integration_id'] . 
            $callbackResponse['is_3d_secure'] . 
            $callbackResponse['is_auth'] . 
            $callbackResponse['is_capture'] . 
            $callbackResponse['is_refunded'] . 
            $callbackResponse['is_standalone_payment'] . 
            $callbackResponse['is_voided'] . 
            $callbackResponse['order'] . 
            $callbackResponse['owner'] . 
            $callbackResponse['pending'] . 
            $callbackResponse['source_data_pan'] . 
            $callbackResponse['source_data_sub_type'] . 
            $callbackResponse['source_data_type'] . 
            $callbackResponse['success'];

        $hmac = hash_hmac('sha512', $string, config('ma-payment.PAYMOB_HMAC'));

        if (!hash_equals($hmac, $callbackResponse['hmac'])) {
            throw new RuntimeException(
                'Invalid Paymob transaction HMAC.'
            );
        }
    }
}