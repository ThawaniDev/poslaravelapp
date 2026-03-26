<?php

namespace App\Domain\Security\Enums;

enum SessionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('security.session_active'),
            self::Expired => __('security.session_expired'),
            self::Revoked => __('security.session_revoked'),
            self::Closed => __('security.session_closed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'gray',
            self::Revoked => 'danger',
            self::Closed => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-signal',
            self::Expired => 'heroicon-o-clock',
            self::Revoked => 'heroicon-o-x-circle',
            self::Closed => 'heroicon-o-minus-circle',
        };
    }
}
