<?php

namespace App\Domain\WameedAI\Enums;

enum AISuggestionPriority: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
}
