<?php

namespace App\Application\Orders\Commands;

final class UpdateOrderStatusCommand
{
    public function __construct(
        public readonly int     $orderId,
        public readonly string  $status,
        public readonly string  $note            = '',
        public readonly ?string $trackingCarrier = null,
        public readonly ?string $trackingNumber  = null,
    ) {}
}
