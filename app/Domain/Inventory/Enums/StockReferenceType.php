<?php

namespace App\Domain\Inventory\Enums;

enum StockReferenceType: string
{
    case GoodsReceipt = 'goods_receipt';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';
    case Transaction = 'transaction';
    case Waste = 'waste';
    case Stocktake = 'stocktake';
    case Recipe = 'recipe';
}
