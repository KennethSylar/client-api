<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Queries\GetOrderQuery;
use App\Domain\Orders\Order;
use App\Domain\Orders\OrderRepositoryInterface;

final class GetOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    public function handle(GetOrderQuery $query): ?Order
    {
        if ($query->id !== null) {
            return $this->orders->findById($query->id);
        }

        if ($query->token !== null) {
            return $this->orders->findByToken($query->token);
        }

        throw new \InvalidArgumentException('GetOrderQuery requires id or token.');
    }
}
