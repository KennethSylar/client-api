<?php

namespace App\Application\Orders\Commands;

final class RecordPaymentCommand
{
    public function __construct(
        public readonly int    $orderId,
        public readonly string $gateway,
        public readonly string $reference,
    ) {}
}
