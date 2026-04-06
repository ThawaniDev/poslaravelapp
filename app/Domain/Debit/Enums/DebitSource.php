<?php

namespace App\Domain\Debit\Enums;

enum DebitSource: string
{
    case PosTerminal = 'pos_terminal';
    case Invoice = 'invoice';
    case Return = 'return';
    case Manual = 'manual';
    case InventorySystem = 'inventory_system';

    public function label(): string
    {
        return match ($this) {
            self::PosTerminal => 'POS Terminal',
            self::Invoice => 'Invoice',
            self::Return => 'Return',
            self::Manual => 'Manual',
            self::InventorySystem => 'Inventory System',
        };
    }
}
