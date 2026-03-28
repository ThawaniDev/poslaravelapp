<?php

namespace App\Domain\Website\Enums;

enum NewsletterStatus: string
{
    case Active = 'active';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Unsubscribed => 'Unsubscribed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Unsubscribed => 'gray',
        };
    }
}
