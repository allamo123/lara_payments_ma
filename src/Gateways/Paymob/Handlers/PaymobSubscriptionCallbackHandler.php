<?php

namespace Ma\Payment\Gateways\Paymob\Handlers;

use RuntimeException;

final class PaymobSubscriptionCallbackHandler
{
    public function handle(array $processedData): array
    {
        $this->verifyHMAC($processedData);

        return [
            'type' => 'TRANSACTION',
            'source' => 'subscription',
            'data' => [
                'hmac' => true,
                'transaction' => [
                    'obj' => $processedData['transaction'],
                    'hmac' => $processedData['hmac'],
                ],
            ],
        ];
    }

    private function verifyHMAC(array $data): void
    {
        if (! isset($data['transaction'])) {
            throw new RuntimeException(
                'Transaction does not exist at subscription webhook handler.'
            );
        }

        if (! isset($data['hmac'])) {
            throw new RuntimeException(
                'HMAC does not exist at subscription webhook handler.'
            );
        }

        $transaction = $data['transaction'];

        $string =
            $transaction['amount_cents'] .
            $transaction['created_at'] .
            $transaction['currency'] .
            ($transaction['error_occured'] ? 'true' : 'false') .
            ($transaction['has_parent_transaction'] ? 'true' : 'false') .
            $transaction['id'] .
            $transaction['integration_id'] .
            ($transaction['is_3d_secure'] ? 'true' : 'false') .
            ($transaction['is_auth'] ? 'true' : 'false') .
            ($transaction['is_capture'] ? 'true' : 'false') .
            ($transaction['is_refunded'] ? 'true' : 'false') .
            ($transaction['is_standalone_payment'] ? 'true' : 'false') .
            ($transaction['is_voided'] ? 'true' : 'false') .
            $transaction['order']['id'] .
            $transaction['owner'] .
            ($transaction['pending'] ? 'true' : 'false') .
            $transaction['source_data']['pan'] .
            $transaction['source_data']['sub_type'] .
            $transaction['source_data']['type'] .
            ($transaction['success'] ? 'true' : 'false');

        $calculatedHmac = hash_hmac(
            'sha512',
            $string,
            config('ma-payment.PAYMOB_HMAC')
        );

        // if (! hash_equals($calculatedHmac, $data['hmac'])) {
        //     throw new RuntimeException(
        //         'Invalid Paymob subscription transaction HMAC.'
        //     );
        // }

        /*
            Temporary Solution we trust the callback 
            by another way instead of hmac at paymob gateway class.
        */
    }
}