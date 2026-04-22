<?php

return [
    // ─── Stock ───────────────────────────────────────────
    'stock_levels_retrieved' => 'Stock levels retrieved',
    'stock_movements_retrieved' => 'Stock movements retrieved',
    'reorder_point_set' => 'Reorder point updated successfully',
    'low_stock_retrieved' => 'Low stock items retrieved',

    // ─── Goods Receipts ──────────────────────────────────
    'goods_receipt_created' => 'Goods receipt created successfully',
    'goods_receipt_confirmed' => 'Goods receipt confirmed',
    'goods_receipt_already_confirmed' => 'Goods receipt is already confirmed',

    // ─── Stock Adjustments ───────────────────────────────
    'adjustment_created' => 'Stock adjustment created successfully',

    // ─── Stock Transfers ─────────────────────────────────
    'transfer_created' => 'Stock transfer created successfully',
    'transfer_approved' => 'Transfer approved',
    'transfer_received' => 'Transfer received',
    'transfer_cancelled' => 'Transfer cancelled',
    'transfer_not_pending' => 'Only pending transfers can be approved',
    'transfer_not_in_transit' => 'Only in-transit transfers can be received',

    // ─── Purchase Orders ─────────────────────────────────
    'po_created' => 'Purchase order created successfully',
    'po_sent' => 'Purchase order sent',
    'po_received' => 'Purchase order received',
    'po_cancelled' => 'Purchase order cancelled',

    // ─── Recipes ─────────────────────────────────────────
    'recipe_created' => 'Recipe created successfully',
    'recipe_updated' => 'Recipe updated successfully',
    'recipe_deleted' => 'Recipe deleted successfully',

    // ─── Stocktakes ──────────────────────────────────────
    'stocktake_created' => 'Stocktake created successfully',
    'stocktake_counts_updated' => 'Stocktake counts updated',
    'stocktake_applied' => 'Stocktake applied successfully',
    'stocktake_cancelled' => 'Stocktake cancelled',
    'stocktake_already_completed' => 'Stocktake is already completed',
    'stocktake_cannot_update' => 'Cannot update counts on a completed or cancelled stocktake',

    // ─── Waste ───────────────────────────────────────────
    'waste_recorded' => 'Waste record created successfully',
    'waste_list_retrieved' => 'Waste records retrieved',

    // ─── Expiry ──────────────────────────────────────────
    'expiry_alerts_retrieved' => 'Expiry alerts retrieved',

    // ─── Errors ──────────────────────────────────────────
    'insufficient_stock' => 'Insufficient stock for :product. Available: :available, Requested: :requested.',
];
