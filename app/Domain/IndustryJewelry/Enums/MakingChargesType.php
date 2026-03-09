<?php

namespace App\Domain\IndustryJewelry\Enums;

enum MakingChargesType: string
{
    case Flat = 'flat';
    case Percentage = 'percentage';
    case PerGram = 'per_gram';
}
