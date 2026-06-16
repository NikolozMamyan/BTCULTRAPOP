<?php

namespace App\Enum;

enum ProductStatus: string
{
    case STANDARD = 'standard';
    case PROMO = 'promo';
    case NEW = 'new';
    case BESTSELLER = 'bestseller';
}
