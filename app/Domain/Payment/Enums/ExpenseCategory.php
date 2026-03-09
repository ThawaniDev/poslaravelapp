<?php

namespace App\Domain\Payment\Enums;

enum ExpenseCategory: string
{
    case Supplies = 'supplies';
    case Food = 'food';
    case Transport = 'transport';
    case Maintenance = 'maintenance';
    case Utility = 'utility';
    case Other = 'other';
}
