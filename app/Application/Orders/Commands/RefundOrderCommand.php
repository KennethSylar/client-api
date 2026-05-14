<?php

namespace App\Application\Orders\Commands;

final class RefundOrderCommand
{
    public function __construct(
        public readonly int    $orderId,
        public readonly string $note = '',
    ) {}
}
