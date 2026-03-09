<?php

namespace App\Domain\ContentOnboarding\Enums;

enum AnimationStyle: string
{
    case Fade = 'fade';
    case Slide = 'slide';
    case None = 'none';
}
