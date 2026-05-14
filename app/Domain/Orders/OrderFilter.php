<?php

namespace App\Domain\Orders;

final class OrderFilter
{
    public function __construct(
        public readonly int    $page    = 1,
        public readonly int    $perPage = 25,
        public readonly string $status  = '',
        public readonly string $search  = '',
    ) {}
}
