<?php

namespace App\Application\Shop\Commands;

final class ReorderProductImagesCommand
{
    /**
     * @param array<int,int> $positions  [imageId => position, ...]
     */
    public function __construct(
        public readonly int   $productId,
        public readonly array $positions,
    ) {}
}
