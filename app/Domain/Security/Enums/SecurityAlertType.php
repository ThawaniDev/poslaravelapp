<?php

namespace App\Domain\Security\Enums;

enum SecurityAlertType: string
{
    case BruteForce = 'brute_force';
    case BulkExport = 'bulk_export';
    case UnusualIp = 'unusual_ip';
    case PermissionEscalation = 'permission_escalation';
    case AfterHoursAccess = 'after_hours_access';
}
