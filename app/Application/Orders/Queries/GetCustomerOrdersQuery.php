<?php

namespace App\Application\Orders\Queries;

final class GetCustomerOrdersQuery
{
    public function __construct(
        public readonly int $customerId,
        public readonly int $page    = 1,
        public readonly int $perPage = 10,
    ) {}
}
