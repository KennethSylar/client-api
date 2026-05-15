<?php

namespace App\Domain\Orders;

final class RefundableItem
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly int $qty,
    ) {}
}
