<?php

namespace App\Application\Shop\Commands;

final class ModerateReviewCommand
{
    public function __construct(
        public readonly int     $reviewId,
        public readonly string  $status,
        public readonly ?string $adminNote,
    ) {}
}
