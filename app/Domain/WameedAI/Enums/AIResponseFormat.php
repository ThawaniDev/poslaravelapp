<?php

namespace App\Domain\WameedAI\Enums;

enum AIResponseFormat: string
{
    case JSON_OBJECT = 'json_object';
    case TEXT = 'text';
}
