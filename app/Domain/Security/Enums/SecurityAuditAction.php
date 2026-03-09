<?php

namespace App\Domain\Security\Enums;

enum SecurityAuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case PinOverride = 'pin_override';
    case FailedLogin = 'failed_login';
    case SettingsChange = 'settings_change';
    case RemoteWipe = 'remote_wipe';
}
