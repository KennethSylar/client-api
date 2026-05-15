<?php

namespace App\Domain\Shop;

use App\Domain\Shared\PaginatedResult;

interface ReviewRepositoryInterface
{
    public function findById(int $id): ?Review;

    public function findByCustomerAndProduct(int $customerId, int $productId): ?Review;

    /**
     * Returns the order_id of a qualifying verified purchase, or null if none exists.
     * Qualifying statuses: paid, processing, shipped, delivered, refunded, partially_refunded.
     */
    public function findVerifiedPurchaseOrderId(int $customerId, int $productId): ?int;

    public function findByProduct(int $productId, string $status, int $page, int $perPage): PaginatedResult;

    public function findPaginated(string $status, int $page, int $perPage): PaginatedResult;

    public function save(Review $review): Review;

    public function updateStatus(int $id, ReviewStatus $status, ?string $adminNote): void;

    public function delete(int $id): void;
}
