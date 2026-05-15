<?php

namespace App\Application\Shop\Queries;

final class ListProductsQuery
{
    public function __construct(
        public readonly int    $page       = 1,
        public readonly int    $perPage    = 25,
        public readonly string $search     = '',
        public readonly ?int   $categoryId = null,
        public readonly bool   $activeOnly = false,
    ) {}
}
