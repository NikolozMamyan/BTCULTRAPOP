<?php

namespace App\Enum;

enum CartStatus: string
{
    case ACTIVE = 'active';
    case CONVERTED = 'converted';
    case ABANDONED = 'abandoned';
}
