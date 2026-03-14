<?php

namespace App\Domain\AdminPanel\Enums;

enum AdminPermissionGroup: string
{
    case Stores = 'Stores';
    case Billing = 'Billing';
    case Tickets = 'Tickets';
    case Integrations = 'Integrations';
    case Settings = 'Settings';
    case Analytics = 'Analytics';
    case Announcements = 'Announcements';
    case Users = 'Users';
    case AppUpdates = 'App Updates';
    case AdminTeam = 'Admin Team';
    case ProviderRoles = 'Provider Roles';
    case Infrastructure = 'Infrastructure';
    case Content = 'Content';
    case Notifications = 'Notifications';
    case UI = 'UI';
    case Security = 'Security';
    case KnowledgeBase = 'Knowledge Base';
}
