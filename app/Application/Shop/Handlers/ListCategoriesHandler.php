<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Queries\ListCategoriesQuery;
use App\Domain\Shop\Category;
use App\Domain\Shop\CategoryRepositoryInterface;

final class ListCategoriesHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    /** @return Category[] */
    public function handle(ListCategoriesQuery $query): array
    {
        return $this->categories->findAll();
    }
}
