<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Queries\GetStockHistoryQuery;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Shop\StockRepositoryInterface;

final class GetStockHistoryHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly StockRepositoryInterface   $stock,
    ) {}

    /** @return array[] */
    public function handle(GetStockHistoryQuery $query): array
    {
        if ($this->products->findById($query->productId) === null) {
            throw new \DomainException('Product not found.');
        }

        return $this->stock->getHistory($query->productId, $query->limit);
    }
}
