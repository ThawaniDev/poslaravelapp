<?php

namespace App\Domain\WameedAI\Enums;

enum AIProvider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GOOGLE = 'google';
}
