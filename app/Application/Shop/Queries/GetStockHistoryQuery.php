<?php

namespace App\Application\Shop\Queries;

final class GetStockHistoryQuery
{
    public function __construct(
        public readonly int $productId,
        public readonly int $limit = 50,
    ) {}
}
