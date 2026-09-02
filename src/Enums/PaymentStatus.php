<?php 

namespace Ma\Payment\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELED = 'canceled';
    case FULLY_REFUNDED = 'fully_refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}