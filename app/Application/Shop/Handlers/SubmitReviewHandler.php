<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\SubmitReviewCommand;
use App\Domain\Shop\Review;
use App\Domain\Shop\ReviewRepositoryInterface;
use App\Domain\Shop\ReviewStatus;
use App\Domain\Shop\ProductRepositoryInterface;

final class SubmitReviewHandler
{
    public function __construct(
        private readonly ReviewRepositoryInterface  $reviews,
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(SubmitReviewCommand $cmd): Review
    {
        $product = $this->products->findById($cmd->productId);
        if ($product === null || !$product->active) {
            throw new \DomainException('Product not found.');
        }

        if ($cmd->rating < 1 || $cmd->rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5.');
        }

        $orderId = $this->reviews->findVerifiedPurchaseOrderId($cmd->customerId, $cmd->productId);
        if ($orderId === null) {
            throw new \DomainException('You must have purchased this product to leave a review.');
        }

        $existing = $this->reviews->findByCustomerAndProduct($cmd->customerId, $cmd->productId);
        if ($existing !== null) {
            throw new \DomainException('You have already reviewed this product.');
        }

        $review = new Review(
            id:         0,
            productId:  $cmd->productId,
            customerId: $cmd->customerId,
            orderId:    $orderId,
            rating:     $cmd->rating,
            title:      trim($cmd->title),
            body:       trim($cmd->body) ?: null,
            status:     ReviewStatus::Pending,
            adminNote:  null,
            createdAt:  new \DateTimeImmutable(),
            updatedAt:  new \DateTimeImmutable(),
        );

        return $this->reviews->save($review);
    }
}
