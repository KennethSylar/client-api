<?php

namespace App\Application\Shop\Commands;

final class CreateProductCommand
{
    public function __construct(
        public readonly string  $name,
        public readonly float   $price,
        public readonly string  $description    = '',
        public readonly ?string $slug           = null,
        public readonly bool    $vatExempt       = false,
        public readonly bool    $trackStock      = true,
        public readonly int     $stockQty        = 0,
        public readonly int     $lowStockThreshold = 5,
        public readonly ?int    $categoryId     = null,
        public readonly bool    $active         = true,
        public readonly ?array  $landingContent = null,
    ) {}
}
