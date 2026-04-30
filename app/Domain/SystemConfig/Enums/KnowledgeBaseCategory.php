<?php

namespace App\Domain\SystemConfig\Enums;

enum KnowledgeBaseCategory: string
{
    case General = 'general';
    case GettingStarted = 'getting_started';
    case PosUsage = 'pos_usage';
    case Inventory = 'inventory';
    case Delivery = 'delivery';
    case Billing = 'billing';
    case Troubleshooting = 'troubleshooting';

    public function label(): string
    {
        return match ($this) {
            self::General        => __('support.kb_cat_general'),
            self::GettingStarted => __('support.kb_cat_getting_started'),
            self::PosUsage       => __('support.kb_cat_pos_usage'),
            self::Inventory      => __('support.kb_cat_inventory'),
            self::Delivery       => __('support.kb_cat_delivery'),
            self::Billing        => __('support.kb_cat_billing'),
            self::Troubleshooting => __('support.kb_cat_troubleshooting'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::GettingStarted => 'info',
            self::PosUsage       => 'primary',
            self::Inventory      => 'warning',
            self::Delivery       => 'success',
            self::Billing        => 'danger',
            self::Troubleshooting => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GettingStarted => 'heroicon-o-rocket-launch',
            self::PosUsage       => 'heroicon-o-computer-desktop',
            self::Inventory      => 'heroicon-o-cube',
            self::Delivery       => 'heroicon-o-truck',
            self::Billing        => 'heroicon-o-credit-card',
            self::Troubleshooting => 'heroicon-o-wrench-screwdriver',
        };
    }
}
