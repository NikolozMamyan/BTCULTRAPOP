<?php

namespace App\Model;

use App\Entity\Product;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminManualOrderItemData
{
    #[Assert\NotNull]
    public ?Product $product = null;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(1000)]
    public int $quantity = 1;

    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100000000)]
    public ?int $unitPriceTaxIncludedCents = null;
}
