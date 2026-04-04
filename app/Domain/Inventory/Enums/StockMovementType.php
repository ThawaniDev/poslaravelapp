<?php

namespace App\Domain\Inventory\Enums;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Sale = 'sale';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Waste = 'waste';
    case RecipeDeduction = 'recipe_deduction';
    case SupplierReturn = 'supplier_return';
}
