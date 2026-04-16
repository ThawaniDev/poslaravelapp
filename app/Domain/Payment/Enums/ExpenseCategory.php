<?php

namespace App\Domain\Payment\Enums;

enum ExpenseCategory: string
{
    case Supplies = 'supplies';
    case Food = 'food';
    case Transport = 'transport';
    case Maintenance = 'maintenance';
    case Utility = 'utility';
    case Cleaning = 'cleaning';
    case Rent = 'rent';
    case Salary = 'salary';
    case Marketing = 'marketing';
    case Other = 'other';
}
