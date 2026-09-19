<?php 

namespace Ma\Payment\Enums;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case DISABLED = 'disabled';
    case SUSPENDED = 'suspended';
}