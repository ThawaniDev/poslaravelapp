<?php

namespace App\Domain\DeliveryIntegration\Enums;

enum MenuSyncStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';
}
