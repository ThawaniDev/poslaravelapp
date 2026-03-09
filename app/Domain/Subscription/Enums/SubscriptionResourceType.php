<?php

namespace App\Domain\Subscription\Enums;

enum SubscriptionResourceType: string
{
    case Products = 'products';
    case Staff = 'staff';
    case Branches = 'branches';
    case TransactionsMonth = 'transactions_month';
}
