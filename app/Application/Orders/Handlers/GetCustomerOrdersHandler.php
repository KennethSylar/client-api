<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Queries\GetCustomerOrdersQuery;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Shared\PaginatedResult;

final class GetCustomerOrdersHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    public function handle(GetCustomerOrdersQuery $query): PaginatedResult
    {
        return $this->orders->findByCustomer($query->customerId, $query->page, $query->perPage);
    }
}
