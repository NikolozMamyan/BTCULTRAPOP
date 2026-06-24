<?php

namespace App\Enum;

enum PromoDiscountType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';
}
