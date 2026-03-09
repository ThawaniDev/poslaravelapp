<?php

namespace App\Domain\Support\Enums;

enum TicketCategory: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Zatca = 'zatca';
    case FeatureRequest = 'feature_request';
    case General = 'general';
}
