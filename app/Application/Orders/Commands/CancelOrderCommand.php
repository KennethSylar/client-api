<?php

namespace App\Application\Orders\Commands;

final class CancelOrderCommand
{
    public function __construct(
        public readonly int    $orderId,
        public readonly string $note = '',
    ) {}
}
