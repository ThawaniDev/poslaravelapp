<?php

namespace App\Domain\Support\Enums;

enum TicketSenderType: string
{
    case Provider = 'provider';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Provider => __('support.sender_provider'),
            self::Admin => __('support.sender_admin'),
        };
    }
}
