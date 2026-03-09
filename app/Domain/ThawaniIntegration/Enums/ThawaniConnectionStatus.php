<?php

namespace App\Domain\ThawaniIntegration\Enums;

enum ThawaniConnectionStatus: string
{
    case Connected = 'connected';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
