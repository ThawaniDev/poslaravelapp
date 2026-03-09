<?php

namespace App\Domain\PlatformAnalytics\Enums;

enum StoreHealthSyncStatus: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Pending = 'pending';
}
