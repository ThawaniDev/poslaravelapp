<?php

namespace App\Domain\SystemConfig\Enums;

enum SystemSettingsGroup: string
{
    case General = 'general';
    case System = 'system';
    case Zatca = 'zatca';
    case Payment = 'payment';
    case Sms = 'sms';
    case Email = 'email';
    case Push = 'push';
    case Whatsapp = 'whatsapp';
    case Sync = 'sync';
    case Vat = 'vat';
    case Locale = 'locale';
    case Maintenance = 'maintenance';
}
