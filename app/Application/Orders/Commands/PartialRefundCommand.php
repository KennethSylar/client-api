<?php

namespace App\Application\Orders\Commands;

final class PartialRefundCommand
{
    /** @param \App\Domain\Orders\RefundableItem[] $items */
    public function __construct(
        public readonly int    $orderId,
        public readonly int    $amountCents,
        public readonly array  $items,
        public readonly string $note = '',
    ) {}
}
