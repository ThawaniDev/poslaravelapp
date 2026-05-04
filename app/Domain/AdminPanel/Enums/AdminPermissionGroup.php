<?php

namespace App\Domain\AdminPanel\Enums;

enum AdminPermissionGroup: string
{
    case Stores = 'stores';
    case Billing = 'billing';
    case Tickets = 'tickets';
    case Integrations = 'integrations';
    case Settings = 'settings';
    case Analytics = 'analytics';
    case Announcements = 'announcements';
    case Users = 'users';
    case AppUpdates = 'app_updates';
    case AdminTeam = 'admin_team';
    case ProviderRoles = 'provider_roles';
    case Infrastructure = 'infrastructure';
    case Content = 'content';
    case Notifications = 'notifications';
    case UI = 'ui';
    case Security = 'security';
    case KnowledgeBase = 'kb';
    case WameedAI = 'wameed_ai';
    case SoftPos = 'softpos';

    public function label(): string
    {
        return match ($this) {
            self::Stores => 'Stores',
            self::Billing => 'Billing',
            self::Tickets => 'Tickets',
            self::Integrations => 'Integrations',
            self::Settings => 'Settings',
            self::Analytics => 'Analytics',
            self::Announcements => 'Announcements',
            self::Users => 'Users',
            self::AppUpdates => 'App Updates',
            self::AdminTeam => 'Admin Team',
            self::ProviderRoles => 'Provider Roles',
            self::Infrastructure => 'Infrastructure',
            self::Content => 'Content',
            self::Notifications => 'Notifications',
            self::UI => 'UI',
            self::Security => 'Security',
            self::KnowledgeBase => 'Knowledge Base',
            self::WameedAI => 'Wameed AI',
            self::SoftPos => 'SoftPOS',
        };
    }
}
