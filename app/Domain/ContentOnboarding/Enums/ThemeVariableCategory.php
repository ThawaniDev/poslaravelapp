<?php

namespace App\Domain\ContentOnboarding\Enums;

enum ThemeVariableCategory: string
{
    case Typography = 'typography';
    case Colors = 'colors';
    case Spacing = 'spacing';
    case Borders = 'borders';
    case Shadows = 'shadows';
    case Animations = 'animations';
}
