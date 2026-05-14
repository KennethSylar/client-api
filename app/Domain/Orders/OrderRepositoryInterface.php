<?php

namespace App\Domain\Orders;

use App\Domain\Shared\PaginatedResult;

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    public function findByToken(string $token): ?Order;

    public function findPaginated(OrderFilter $filter): PaginatedResult;

    public function findByCustomer(int $customerId, int $page = 1, int $perPage = 25): PaginatedResult;

    /** Persists a new order (items included). Returns the order with its generated ID. */
    public function save(Order $order): Order;

    /**
     * Patches status + optional tracking fields without a full entity round-trip.
     * @param array<string,mixed> $extra  e.g. ['tracking_carrier'=>'...','paid_at'=>'...']
     */
    public function updateStatus(int $id, OrderStatus $status, array $extra = []): void;

    public function appendStatusLog(OrderStatusLogEntry $entry): void;
}
