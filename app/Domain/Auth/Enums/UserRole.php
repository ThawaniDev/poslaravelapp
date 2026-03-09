<?php

namespace App\Domain\Auth\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case ChainManager = 'chain_manager';
    case BranchManager = 'branch_manager';
    case Cashier = 'cashier';
    case InventoryClerk = 'inventory_clerk';
    case Accountant = 'accountant';
    case KitchenStaff = 'kitchen_staff';
}
