<?php

namespace App\Domain\SystemConfig\Enums;

enum KnowledgeBaseCategory: string
{
    case GettingStarted = 'getting_started';
    case PosUsage = 'pos_usage';
    case Inventory = 'inventory';
    case Delivery = 'delivery';
    case Billing = 'billing';
    case Troubleshooting = 'troubleshooting';
}
