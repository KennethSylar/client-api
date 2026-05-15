<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\ModerateReviewCommand;
use App\Domain\Shop\ReviewRepositoryInterface;
use App\Domain\Shop\ReviewStatus;

final class ModerateReviewHandler
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
    ) {}

    public function handle(ModerateReviewCommand $cmd): void
    {
        $review = $this->reviews->findById($cmd->reviewId);
        if ($review === null) {
            throw new \DomainException('Review not found.');
        }

        $status = ReviewStatus::tryFrom($cmd->status);
        if ($status === null || $status === ReviewStatus::Pending) {
            throw new \InvalidArgumentException("Invalid moderation status: {$cmd->status}");
        }

        $this->reviews->updateStatus($cmd->reviewId, $status, $cmd->adminNote);
    }
}
