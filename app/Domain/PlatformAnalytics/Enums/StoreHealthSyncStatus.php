<?php

namespace App\Domain\PlatformAnalytics\Enums;

enum StoreHealthSyncStatus: string
{
    case Ok = 'ok';
    case Healthy = 'healthy';
    case Error = 'error';
    case Pending = 'pending';
    case Warning = 'warning';
    case Critical = 'critical';
    case Degraded = 'degraded';
}
