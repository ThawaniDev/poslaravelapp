<?php

namespace App\Domain\ContentOnboarding\Enums;

enum CfdIdleLayout: string
{
    case Slideshow = 'slideshow';
    case StaticImage = 'static_image';
    case VideoLoop = 'video_loop';
}
