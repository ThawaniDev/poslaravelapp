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
}
