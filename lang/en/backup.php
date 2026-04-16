<?php

return [
    'created' => 'Backup created successfully.',
    'list_retrieved' => 'Backup list retrieved.',
    'details_retrieved' => 'Backup details retrieved.',
    'not_found' => 'Backup not found.',
    'restore_initiated' => 'Backup restore initiated.',
    'only_completed_backups_can_be_restored' => 'Only completed backups can be restored.',
    'verified' => 'Backup integrity verified.',
    'schedule_retrieved' => 'Backup schedule retrieved.',
    'schedule_updated' => 'Backup schedule updated.',
    'storage_retrieved' => 'Storage usage retrieved.',
    'deleted' => 'Backup deleted successfully.',
    'export_created' => 'Data export created.',
    'provider_status_retrieved' => 'Provider backup status retrieved.',

    // ── Enum labels ──────────────────────────────────────────
    'enums' => [
        'backup_type' => [
            'auto' => 'Automatic',
            'manual' => 'Manual',
            'pre_update' => 'Pre-Update',
            'full' => 'Full',
            'incremental' => 'Incremental',
        ],
        'sync_direction' => [
            'push' => 'Push',
            'pull' => 'Pull',
            'full' => 'Full Sync',
            'upload' => 'Upload',
            'download' => 'Download',
        ],
    ],
];
