<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Queries\ListReviewsQuery;
use App\Domain\Shop\ReviewRepositoryInterface;
use App\Domain\Shared\PaginatedResult;

final class ListReviewsHandler
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
    ) {}

    public function handle(ListReviewsQuery $query): PaginatedResult
    {
        if ($query->productId !== null) {
            return $this->reviews->findByProduct(
                $query->productId,
                $query->status,
                $query->page,
                $query->perPage,
            );
        }

        return $this->reviews->findPaginated($query->status, $query->page, $query->perPage);
    }
}
