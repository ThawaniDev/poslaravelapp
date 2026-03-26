<?php

namespace App\Domain\DeliveryIntegration\Enums;

enum DeliveryConfigPlatform: string
{
    case Hungerstation = 'hungerstation';
    case Jahez = 'jahez';
    case Marsool = 'marsool';
    case Keeta = 'keeta';
    case NoonFood = 'noon_food';
    case Ninja = 'ninja';
    case TheChefz = 'the_chefz';
    case Talabat = 'talabat';
    case ToYou = 'toyou';
    case Carriage = 'carriage';

    public function label(): string
    {
        return match ($this) {
            self::Hungerstation => 'HungerStation',
            self::Jahez => 'Jahez',
            self::Marsool => 'Marsool',
            self::Keeta => 'Keeta (STC)',
            self::NoonFood => 'Noon Food',
            self::Ninja => 'Ninja',
            self::TheChefz => 'The Chefz',
            self::Talabat => 'Talabat',
            self::ToYou => 'ToYou',
            self::Carriage => 'Carriage',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Hungerstation => '#FF5A00',
            self::Jahez => '#00C853',
            self::Marsool => '#1E88E5',
            self::Keeta => '#7C3AED',
            self::NoonFood => '#FFCC00',
            self::Ninja => '#E53935',
            self::TheChefz => '#8D6E63',
            self::Talabat => '#FF6F00',
            self::ToYou => '#00ACC1',
            self::Carriage => '#43A047',
        };
    }

    /** Whether this platform uses store-managed credentials (vs platform-managed) */
    public function isStoreManagedCredentials(): bool
    {
        return in_array($this, [self::Hungerstation, self::Jahez, self::Marsool]);
    }
}
