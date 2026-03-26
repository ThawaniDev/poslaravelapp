<?php

namespace App\Domain\ContentOnboarding\Enums;

enum ThemeVariableType: string
{
    case Color = 'color';
    case Size = 'size';
    case Font = 'font';
    case Spacing = 'spacing';
    case Opacity = 'opacity';
    case Shadow = 'shadow';
    case BorderRadius = 'border_radius';
}
