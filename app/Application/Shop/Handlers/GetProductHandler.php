<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Queries\GetProductQuery;
use App\Domain\Shop\Product;
use App\Domain\Shop\ProductRepositoryInterface;

final class GetProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(GetProductQuery $query): ?Product
    {
        if ($query->id !== null) {
            return $this->products->findById($query->id);
        }

        if ($query->slug !== null) {
            return $this->products->findBySlug($query->slug);
        }

        throw new \InvalidArgumentException('GetProductQuery requires id or slug.');
    }
}
