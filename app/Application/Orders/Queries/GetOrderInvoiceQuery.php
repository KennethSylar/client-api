<?php

namespace App\Application\Orders\Queries;

final class GetOrderInvoiceQuery
{
    public function __construct(
        public readonly int $orderId,
    ) {}
}
