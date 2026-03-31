<?php

namespace App\Domain\Inventory\Enums;

enum WasteReason: string
{
    case Expired = 'expired';
    case Damaged = 'damaged';
    case Spillage = 'spillage';
    case Overproduction = 'overproduction';
    case QualityIssue = 'quality_issue';
    case Other = 'other';
}
