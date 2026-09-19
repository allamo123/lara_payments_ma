<?php

namespace Ma\Payment\Gateways\Paymob\Handlers;

use Illuminate\Support\Facades\Log;
use RuntimeException;

final class PaymobTransactionCallbackHandler
{
    public function handle(array $proccessedData): array
    {
        Log::info('payload_show', [
            'issue_daata' =>  $proccessedData,
        ]);

        $data = $proccessedData;

        $type = $data['type'] ?? null;

        $webhookData = match ($type) {
           'TRANSACTION'  => $this->verifyHMAC($data),
           'TOKEN'  => $this->cardDetails($data['obj']),
            default => throw new RuntimeException('type not exist at webhook handler'),
        };

        return [
            'type' => $type,
            'source' => 'payment',
            'data' => $webhookData,
        ];
    }

    public function verifyHMAC(array $transaction): array
    {
        $string = 
            $transaction['obj']['amount_cents'] . 
            $transaction['obj']['created_at'] . 
            $transaction['obj']['currency'] . 
            ($transaction['obj']['error_occured'] ? 'true' : 'false') . 
            ($transaction['obj']['has_parent_transaction'] ? 'true' : 'false') . 
            $transaction['obj']['id'] . 
            $transaction['obj']['integration_id'] . 
            ($transaction['obj']['is_3d_secure'] ? 'true' : 'false') . 
            ($transaction['obj']['is_auth'] ? 'true' : 'false') . 
            ($transaction['obj']['is_capture'] ? 'true' : 'false') . 
            ($transaction['obj']['is_refunded'] ? 'true' : 'false') . 
            ($transaction['obj']['is_standalone_payment'] ? 'true' : 'false') . 
            ($transaction['obj']['is_voided'] ? 'true' : 'false') . 
            $transaction['obj']['order']['id'] . 
            $transaction['obj']['owner'] . 
            ($transaction['obj']['pending'] ? 'true' : 'false') . 
            $transaction['obj']['source_data']['pan'] . 
            $transaction['obj']['source_data']['sub_type'] . 
            $transaction['obj']['source_data']['type'] . 
            ($transaction['obj']['success'] ? 'true' : 'false');

        $calculatedHmac = hash_hmac('sha512', $string, config('ma-payment.PAYMOB_HMAC'));

        if (!hash_equals($calculatedHmac, $transaction['hmac'])) {
            throw new RuntimeException(
                'Invalid Paymob transaction HMAC.'
            );
        }

        return [
            'hmac' => true,
            'transaction' => $transaction
        ];
    }

    public function cardDetails(array $cardDetails): array
    {
        return $cardDetails;
    }
}