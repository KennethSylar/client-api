<?php

namespace App\Domain\Shop;

final class ProductFilter
{
    public function __construct(
        public readonly int     $page       = 1,
        public readonly int     $perPage    = 24,
        public readonly string  $search     = '',
        public readonly ?int    $categoryId = null,
        public readonly bool    $activeOnly = false,
    ) {}
}
