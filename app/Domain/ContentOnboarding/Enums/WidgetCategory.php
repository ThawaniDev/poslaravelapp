<?php

namespace App\Domain\ContentOnboarding\Enums;

enum WidgetCategory: string
{
    case Core = 'core';
    case Commerce = 'commerce';
    case Display = 'display';
    case Utility = 'utility';
    case Custom = 'custom';
}
