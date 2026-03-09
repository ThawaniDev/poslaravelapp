<?php

namespace App\Domain\AccountingIntegration\Enums;

enum AccountingExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
}
