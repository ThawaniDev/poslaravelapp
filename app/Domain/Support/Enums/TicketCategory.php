<?php

namespace App\Domain\Support\Enums;

enum TicketCategory: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Zatca = 'zatca';
    case FeatureRequest = 'feature_request';
    case General = 'general';
    case Hardware = 'hardware';

    public function label(): string
    {
        return match ($this) {
            self::Billing => __('support.category_billing'),
            self::Technical => __('support.category_technical'),
            self::Zatca => __('support.category_zatca'),
            self::FeatureRequest => __('support.category_feature_request'),
            self::General => __('support.category_general'),
            self::Hardware => __('support.category_hardware'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Billing => 'success',
            self::Technical => 'info',
            self::Zatca => 'warning',
            self::FeatureRequest => 'primary',
            self::General => 'gray',
            self::Hardware => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Billing => 'heroicon-o-credit-card',
            self::Technical => 'heroicon-o-wrench-screwdriver',
            self::Zatca => 'heroicon-o-document-text',
            self::FeatureRequest => 'heroicon-o-light-bulb',
            self::General => 'heroicon-o-question-mark-circle',
            self::Hardware => 'heroicon-o-cpu-chip',
        };
    }
}
