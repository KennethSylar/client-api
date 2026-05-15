<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Queries\ListOrdersQuery;
use App\Domain\Orders\OrderFilter;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Shared\PaginatedResult;

final class ListOrdersHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    public function handle(ListOrdersQuery $query): PaginatedResult
    {
        return $this->orders->findPaginated(new OrderFilter(
            page:    $query->page,
            perPage: $query->perPage,
            status:  $query->status,
            search:  $query->search,
        ));
    }
}
