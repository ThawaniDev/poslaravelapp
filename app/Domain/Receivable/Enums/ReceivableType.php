<?php

namespace App\Domain\Receivable\Enums;

enum ReceivableType: string
{
    case CreditSale = 'credit_sale';
    case Loan = 'loan';
    case InventoryAdjustment = 'inventory_adjustment';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::CreditSale => 'Credit Sale',
            self::Loan => 'Loan',
            self::InventoryAdjustment => 'Inventory Adjustment',
            self::Manual => 'Manual',
        };
    }
}
