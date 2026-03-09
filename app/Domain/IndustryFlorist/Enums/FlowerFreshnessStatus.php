<?php

namespace App\Domain\IndustryFlorist\Enums;

enum FlowerFreshnessStatus: string
{
    case Fresh = 'fresh';
    case MarkedDown = 'marked_down';
    case Disposed = 'disposed';
}
