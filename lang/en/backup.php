<?php

return [
    'created'                                 => 'Backup created successfully.',
    'list_retrieved'                          => 'Backup list retrieved.',
    'details_retrieved'                       => 'Backup details retrieved.',
    'not_found'                               => 'Backup not found.',
    'restore_initiated'                       => 'Backup restore initiated.',
    'only_completed_backups_can_be_restored'  => 'Only completed backups can be restored.',
    'verified'                                => 'Backup integrity verified.',
    'schedule_retrieved'                      => 'Backup schedule retrieved.',
    'schedule_updated'                        => 'Backup schedule updated successfully.',
    'storage_retrieved'                       => 'Storage usage retrieved.',
    'deleted'                                 => 'Backup deleted successfully.',
    'export_created'                          => 'Data export created successfully.',
    'provider_status_retrieved'               => 'Provider backup status retrieved.',
    'invalid_checksum'                        => 'Backup integrity check failed — checksum mismatch.',
    'encryption_key_required'                 => 'Encryption key is required to decrypt this backup.',
    'restore_completed'                       => 'Data has been restored successfully.',
    'restore_failed'                          => 'Restore failed: :reason',
    'concurrent_backup_blocked'               => 'A backup is already in progress. Please wait.',

    // ── Enum labels ──────────────────────────────────────────
    'enums' => [
        'backup_type' => [
            'auto'        => 'Automatic',
            'manual'      => 'Manual',
            'pre_update'  => 'Pre-Update',
            'full'        => 'Full',
            'incremental' => 'Incremental',
        ],
        'storage_location' => [
            'local' => 'Local',
            'cloud' => 'Cloud',
            'both'  => 'Local & Cloud',
        ],
        'status' => [
            'completed' => 'Completed',
            'failed'    => 'Failed',
            'corrupted' => 'Corrupted',
        ],
        'frequency' => [
            'hourly' => 'Hourly',
            'daily'  => 'Daily',
            'weekly' => 'Weekly',
        ],
        'sync_direction' => [
            'push'     => 'Push',
            'pull'     => 'Pull',
            'full'     => 'Full Sync',
            'upload'   => 'Upload',
            'download' => 'Download',
        ],
        'export_format' => [
            'json' => 'JSON',
            'csv'  => 'CSV',
        ],
    ],
];

