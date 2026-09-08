<?php

namespace App\Jobs;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Ma\Payment\Exceptions\RefundTransactionNotFoundException;
use Ma\Payment\Repositories\RefundTransactionRepository;

class UpdateRefundTransaction implements ShouldQueue
{
    use Queueable;
    
    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $refundId,
        private readonly string $refundType,
    ) {}

    public function backoff(): array
    {
        return [2, 4, 5, 6, 7];
    }

    /**
     * Execute the job.
     */
    public function handle(
        RefundTransactionRepository $repository
    ): void {
        $refund = $repository->getRefundTransaction($this->refundId);

        if (!$refund) {
            throw new RefundTransactionNotFoundException($this->refundId);
        }

        $refund->update([
            'refund_type' => $this->refundType,
        ]);
    }
}
