<?php

namespace App\Domain\WameedAI\Enums;

enum AIRequestStatus: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case RATE_LIMITED = 'rate_limited';
    case CACHED = 'cached';
}
