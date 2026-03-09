<?php

namespace App\Domain\AdminPanel\Enums;

enum AdminRoleSlug: string
{
    case SuperAdmin = 'super_admin';
    case PlatformManager = 'platform_manager';
    case SupportAgent = 'support_agent';
    case FinanceAdmin = 'finance_admin';
    case IntegrationManager = 'integration_manager';
    case Sales = 'sales';
    case Viewer = 'viewer';
}
