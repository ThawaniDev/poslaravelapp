<?php

namespace App\Domain\Subscription\Enums;

enum SubscriptionResourceType: string
{
    case Products = 'products';
    case Staff = 'staff';
    case StaffMembers = 'staff_members';
    case CashierTerminals = 'cashier_terminals';
    case Branches = 'branches';
    case TransactionsMonth = 'transactions_month';
    case TransactionsPerMonth = 'transactions_per_month';
    case StorageMb = 'storage_mb';
    case PdfReportsPerMonth = 'pdf_reports_per_month';
}
