<?php

namespace App\Application\Orders\DTOs;

use App\Domain\Orders\Order;

final class PlaceOrderResult
{
    public function __construct(
        public readonly Order  $order,
        public readonly string $paymentGateway,
    ) {}
}
