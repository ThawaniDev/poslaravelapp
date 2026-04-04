<?php

namespace App\Domain\Inventory\Enums;

enum SupplierReturnStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
