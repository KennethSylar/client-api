<?php

namespace App\Application\Orders\DTOs;

final class CartItemDTO
{
    public function __construct(
        public readonly int  $productId,
        public readonly ?int $variantId,
        public readonly int  $qty,
    ) {}
}
