<?php 

namespace Ma\Payment\Enums;

enum SubscriptionTransactionType: string
{
    case INITIAL = 'initial';
    case RENEWAL = 'renewal';
}