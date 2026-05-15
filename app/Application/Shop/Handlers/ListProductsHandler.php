<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Queries\ListProductsQuery;
use App\Domain\Shop\ProductFilter;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Shared\PaginatedResult;

final class ListProductsHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(ListProductsQuery $query): PaginatedResult
    {
        return $this->products->findAll(new ProductFilter(
            page:       $query->page,
            perPage:    $query->perPage,
            search:     $query->search,
            categoryId: $query->categoryId,
            activeOnly: $query->activeOnly,
        ));
    }
}
