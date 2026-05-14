<?php

namespace App\Application\Shop\Commands;

final class UpdateProductCommand
{
    public function __construct(
        public readonly int     $id,
        public readonly ?string $name              = null,
        public readonly ?float  $price             = null,
        public readonly ?string $description       = null,
        public readonly ?string $slug              = null,
        public readonly ?bool   $vatExempt         = null,
        public readonly ?bool   $trackStock        = null,
        public readonly ?int    $stockQty          = null,
        public readonly ?int    $lowStockThreshold = null,
        public readonly bool    $setCategoryId     = false,
        public readonly ?int    $categoryId        = null,
        public readonly ?bool   $active            = null,
        public readonly bool    $setLandingContent = false,
        public readonly ?array  $landingContent    = null,
    ) {}
}
