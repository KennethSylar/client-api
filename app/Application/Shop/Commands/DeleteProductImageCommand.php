<?php

namespace App\Application\Shop\Commands;

final class DeleteProductImageCommand
{
    public function __construct(
        public readonly int $productId,
        public readonly int $imageId,
    ) {}
}
