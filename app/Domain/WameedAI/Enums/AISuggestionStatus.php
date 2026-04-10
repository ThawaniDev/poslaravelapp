<?php

namespace App\Domain\WameedAI\Enums;

enum AISuggestionStatus: string
{
    case PENDING = 'pending';
    case VIEWED = 'viewed';
    case ACCEPTED = 'accepted';
    case DISMISSED = 'dismissed';
    case EXPIRED = 'expired';
}
