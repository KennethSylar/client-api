<?php

namespace App\Application\Shop\Commands;

final class AdjustStockCommand
{
    public function __construct(
        public readonly int     $productId,
        public readonly ?int    $variantId = null,
        /** 'set' | 'adjust' */
        public readonly string  $mode      = 'adjust',
        /** Used when mode='adjust' */
        public readonly int     $delta     = 0,
        /** Used when mode='set' */
        public readonly ?int    $qty       = null,
        public readonly string  $note      = '',
    ) {}
}
