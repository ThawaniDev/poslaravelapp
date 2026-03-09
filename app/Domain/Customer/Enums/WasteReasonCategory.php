<?php

namespace App\Domain\Customer\Enums;

enum WasteReasonCategory: string
{
    case Spoilage = 'spoilage';
    case Damage = 'damage';
    case Theft = 'theft';
    case Sampling = 'sampling';
    case Operational = 'operational';
}
