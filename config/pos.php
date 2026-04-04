<?php

return [
    // Default currency
    'currency' => 'SAR',
    'currency_symbol' => 'ر.س.',
    'decimal_places' => 3,

    // Rounding
    'rounding_method' => 'round_half_up',

    // Receipt defaults
    'receipt' => [
        'paper_width' => 80, // mm
        'show_logo' => true,
        'show_barcode' => true,
        'footer_text' => 'Thank you for your purchase!',
    ],

    // Session defaults
    'session' => [
        'auto_close_hours' => 24,
        'require_cash_count' => true,
    ],

    // Sync
    'sync' => [
        'interval_seconds' => 30,
        'max_offline_hours' => 72,
        'conflict_resolution' => 'server_wins',
    ],
];
