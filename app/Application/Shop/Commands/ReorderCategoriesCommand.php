<?php

namespace App\Application\Shop\Commands;

final class ReorderCategoriesCommand
{
    /**
     * @param array<int,int> $positions  [categoryId => position, ...]
     */
    public function __construct(
        public readonly array $positions,
    ) {}
}
