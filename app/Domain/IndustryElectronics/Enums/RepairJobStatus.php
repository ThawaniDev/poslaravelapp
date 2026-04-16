<?php

namespace App\Domain\IndustryElectronics\Enums;

enum RepairJobStatus: string
{
    case Received = 'received';
    case Diagnosing = 'diagnosing';
    case InProgress = 'in_progress';
    case Repairing = 'repairing';
    case Testing = 'testing';
    case Ready = 'ready';
    case Collected = 'collected';
    case Cancelled = 'cancelled';
}
