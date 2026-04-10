<?php

namespace App\Domain\WameedAI\Enums;

enum AIFeatureCategory: string
{
    case INVENTORY = 'inventory';
    case SALES = 'sales';
    case OPERATIONS = 'operations';
    case CATALOG = 'catalog';
    case CUSTOMER = 'customer';
    case COMMUNICATION = 'communication';
    case FINANCIAL = 'financial';
    case PLATFORM = 'platform';
}
