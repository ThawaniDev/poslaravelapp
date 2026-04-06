<?php

namespace App\Domain\Debit\Enums;

enum DebitType: string
{
    case CustomerCredit = 'customer_credit';
    case SupplierReturn = 'supplier_return';
    case InventoryAdjustment = 'inventory_adjustment';
    case ManualCredit = 'manual_credit';

    public function label(): string
    {
        return match ($this) {
            self::CustomerCredit => 'Customer Credit',
            self::SupplierReturn => 'Supplier Return',
            self::InventoryAdjustment => 'Inventory Adjustment',
            self::ManualCredit => 'Manual Credit',
        };
    }
}
