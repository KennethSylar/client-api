<?php

namespace App\Application\Shop\Commands;

final class SubmitReviewCommand
{
    public function __construct(
        public readonly int    $customerId,
        public readonly int    $productId,
        public readonly int    $rating,
        public readonly string $title,
        public readonly string $body,
    ) {}
}
