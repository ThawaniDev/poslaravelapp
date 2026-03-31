<?php

return [
    // CRUD
    'created'     => 'Terminal created successfully.',
    'updated'     => 'Terminal updated successfully.',
    'deleted'     => 'Terminal deleted successfully.',
    'activated'   => 'Terminal activated.',
    'deactivated' => 'Terminal deactivated.',

    // Validation
    'store_required'         => 'A store is required.',
    'store_not_found'        => 'The selected store does not exist.',
    'name_required'          => 'Terminal name is required.',
    'device_id_required'     => 'Device ID is required.',
    'device_id_taken'        => 'This device ID is already registered.',
    'platform_required'      => 'Platform is required.',
    'platform_invalid'       => 'Invalid platform. Choose from: Windows, macOS, iOS, Android.',
    'acquirer_source_invalid' => 'Invalid acquirer source. Choose from: HALA, Al Rajhi, SNB, Geidea, Other.',
    'softpos_status_invalid' => 'Invalid SoftPOS status. Choose from: Pending, Active, Suspended, Deactivated.',
    'fee_profile_invalid'    => 'Invalid fee profile. Choose from: Standard, Custom, Promotional.',
    'iban_too_long'          => 'IBAN must not exceed 34 characters.',

    // SoftPOS
    'softpos_activated'    => 'SoftPOS activated successfully.',
    'softpos_suspended'    => 'SoftPOS suspended.',
    'softpos_deactivated'  => 'SoftPOS deactivated.',
    'softpos_no_tid'       => 'Cannot activate SoftPOS: Terminal ID (TID) is not configured.',
    'softpos_no_acquirer'  => 'Cannot activate SoftPOS: Acquirer source is not configured.',
    'fees_updated'         => 'Transaction fee configuration updated.',

    // Labels (for admin UI)
    'terminal'              => 'Terminal',
    'terminals'             => 'Terminals',
    'softpos'               => 'SoftPOS',
    'softpos_settings'      => 'SoftPOS Settings',
    'nearpay_tid'           => 'NearPay Terminal ID (TID)',
    'nearpay_mid'           => 'Merchant ID (MID)',
    'acquirer_source'       => 'Acquirer Source',
    'acquirer_name'         => 'Acquirer Name',
    'acquirer_reference'    => 'Acquirer Reference',
    'device_model'          => 'Device Model',
    'os_version'            => 'OS Version',
    'nfc_capable'           => 'NFC Capable',
    'serial_number'         => 'Serial Number',
    'fee_profile'           => 'Fee Profile',
    'fee_mada'              => 'mada Fee (%)',
    'fee_visa_mc'           => 'Visa/MC Fee (%)',
    'fee_flat'              => 'Flat Fee per Txn (SAR)',
    'wameed_margin'        => 'Wameed Margin (%)',
    'settlement_cycle'      => 'Settlement Cycle',
    'settlement_bank'       => 'Settlement Bank',
    'settlement_iban'       => 'Settlement IBAN',
    'softpos_status'        => 'SoftPOS Status',
    'admin_notes'           => 'Admin Notes',
    'last_transaction_at'   => 'Last Transaction',
    'softpos_activated_at'  => 'SoftPOS Activated At',

    // Acquirer labels
    'acquirer_hala'        => 'HALA',
    'acquirer_bank_rajhi'  => 'Al Rajhi Bank',
    'acquirer_bank_snb'    => 'SNB (Saudi National Bank)',
    'acquirer_geidea'      => 'Geidea',
    'acquirer_other'       => 'Other',

    // Fee profiles
    'fee_standard'     => 'Standard',
    'fee_custom'       => 'Custom',
    'fee_promotional'  => 'Promotional',

    // SoftPOS statuses
    'status_pending'      => 'Pending',
    'status_active'       => 'Active',
    'status_suspended'    => 'Suspended',
    'status_deactivated'  => 'Deactivated',
];
