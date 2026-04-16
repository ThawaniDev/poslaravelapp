<?php

namespace App\Domain\Auth\Enums;

enum UserTheme: string
{
    case SystemDefault = 'default';
    case LightClassic = 'light_classic';
    case DarkMode = 'dark_mode';
    case HighContrast = 'high_contrast';
    case ThawaniBrand = 'thawani_brand';
    case Custom = 'custom';
}
