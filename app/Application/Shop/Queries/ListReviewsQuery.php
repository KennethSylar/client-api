<?php

namespace App\Application\Shop\Queries;

final class ListReviewsQuery
{
    public function __construct(
        public readonly ?int   $productId = null,
        public readonly string $status    = 'approved',
        public readonly int    $page      = 1,
        public readonly int    $perPage   = 20,
    ) {}
}
