<?php

namespace App\Model;

use App\Entity\Order;
use Symfony\Component\Validator\Constraints as Assert;

final class AdminOrderExportData
{
    /**
     * @var list<Order>
     */
    #[Assert\Count(
        min: 1,
        max: 200,
        minMessage: 'admin.order.export.error.selection_required',
        maxMessage: 'admin.order.export.error.too_many_orders',
    )]
    public array $orders = [];
}
