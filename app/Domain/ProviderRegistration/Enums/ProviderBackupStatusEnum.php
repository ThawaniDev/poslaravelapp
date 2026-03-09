<?php

namespace App\Domain\ProviderRegistration\Enums;

enum ProviderBackupStatusEnum: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Critical = 'critical';
    case Unknown = 'unknown';
}
