<?php

return [
    // Pharmacy
    'prescriptions_retrieved' => 'Prescriptions retrieved successfully.',
    'prescription_created' => 'Prescription created successfully.',
    'prescription_updated' => 'Prescription updated successfully.',
    'drug_schedules_retrieved' => 'Drug schedules retrieved successfully.',
    'drug_schedule_created' => 'Drug schedule created successfully.',
    'drug_schedule_updated' => 'Drug schedule updated successfully.',

    // Jewelry
    'metal_rates_retrieved' => 'Metal rates retrieved successfully.',
    'metal_rate_saved' => 'Metal rate saved successfully.',
    'jewelry_details_retrieved' => 'Jewelry details retrieved successfully.',
    'jewelry_detail_created' => 'Jewelry detail created successfully.',
    'jewelry_detail_updated' => 'Jewelry detail updated successfully.',
    'buybacks_retrieved' => 'Buyback transactions retrieved successfully.',
    'buyback_created' => 'Buyback transaction created successfully.',

    // Electronics
    'imei_records_retrieved' => 'IMEI records retrieved successfully.',
    'imei_record_created' => 'IMEI record created successfully.',
    'imei_record_updated' => 'IMEI record updated successfully.',
    'repair_jobs_retrieved' => 'Repair jobs retrieved successfully.',
    'repair_job_created' => 'Repair job created successfully.',
    'repair_job_updated' => 'Repair job updated successfully.',
    'repair_job_status_updated' => 'Repair job status updated successfully.',
    'trade_ins_retrieved' => 'Trade-in records retrieved successfully.',
    'trade_in_created' => 'Trade-in record created successfully.',

    // Florist
    'arrangements_retrieved' => 'Flower arrangements retrieved successfully.',
    'arrangement_created' => 'Flower arrangement created successfully.',
    'arrangement_updated' => 'Flower arrangement updated successfully.',
    'arrangement_deleted' => 'Flower arrangement deleted successfully.',
    'freshness_logs_retrieved' => 'Freshness logs retrieved successfully.',
    'freshness_log_created' => 'Freshness log created successfully.',
    'freshness_log_updated' => 'Freshness log status updated successfully.',
    'subscriptions_retrieved' => 'Flower subscriptions retrieved successfully.',
    'subscription_created' => 'Flower subscription created successfully.',
    'subscription_updated' => 'Flower subscription updated successfully.',
    'subscription_toggled' => 'Flower subscription toggled successfully.',

    // Bakery
    'recipes_retrieved' => 'Recipes retrieved successfully.',
    'recipe_created' => 'Recipe created successfully.',
    'recipe_updated' => 'Recipe updated successfully.',
    'recipe_deleted' => 'Recipe deleted successfully.',
    'schedules_retrieved' => 'Production schedules retrieved successfully.',
    'schedule_created' => 'Production schedule created successfully.',
    'schedule_updated' => 'Production schedule updated successfully.',
    'schedule_status_updated' => 'Production schedule status updated successfully.',
    'cake_orders_retrieved' => 'Custom cake orders retrieved successfully.',
    'cake_order_created' => 'Custom cake order created successfully.',
    'cake_order_updated' => 'Custom cake order updated successfully.',
    'cake_order_status_updated' => 'Custom cake order status updated successfully.',

    // Restaurant
    'tables_retrieved' => 'Tables retrieved successfully.',
    'table_created' => 'Table created successfully.',
    'table_updated' => 'Table updated successfully.',
    'table_status_updated' => 'Table status updated successfully.',
    'kitchen_tickets_retrieved' => 'Kitchen tickets retrieved successfully.',
    'kitchen_ticket_created' => 'Kitchen ticket created successfully.',
    'kitchen_ticket_status_updated' => 'Kitchen ticket status updated successfully.',
    'reservations_retrieved' => 'Reservations retrieved successfully.',
    'reservation_created' => 'Reservation created successfully.',
    'reservation_updated' => 'Reservation updated successfully.',
    'reservation_status_updated' => 'Reservation status updated successfully.',
    'tabs_retrieved' => 'Open tabs retrieved successfully.',
    'tab_opened' => 'Tab opened successfully.',
    'tab_closed' => 'Tab closed successfully.',

    // ── Enum labels ──────────────────────────────────────────
    'enums' => [
        'custom_cake_order_status' => [
            'ordered' => 'Ordered',
            'in_progress' => 'In Progress',
            'in_production' => 'In Production',
            'ready' => 'Ready',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ],
        'production_schedule_status' => [
            'scheduled' => 'Scheduled',
            'planned' => 'Planned',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        'repair_job_status' => [
            'received' => 'Received',
            'diagnosing' => 'Diagnosing',
            'in_progress' => 'In Progress',
            'repairing' => 'Repairing',
            'testing' => 'Testing',
            'ready' => 'Ready',
            'collected' => 'Collected',
            'cancelled' => 'Cancelled',
        ],
        'kitchen_ticket_status' => [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'preparing' => 'Preparing',
            'ready' => 'Ready',
            'served' => 'Served',
            'cancelled' => 'Cancelled',
        ],
        'condition_grade' => [
            'new' => 'New',
            'A' => 'Grade A',
            'B' => 'Grade B',
            'C' => 'Grade C',
            'D' => 'Grade D',
        ],
        'flower_freshness_status' => [
            'fresh' => 'Fresh',
            'markdown' => 'Markdown',
            'disposed' => 'Disposed',
        ],
    ],
];
