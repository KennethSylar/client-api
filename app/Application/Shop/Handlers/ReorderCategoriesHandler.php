<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\ReorderCategoriesCommand;
use App\Domain\Shop\CategoryRepositoryInterface;

final class ReorderCategoriesHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function handle(ReorderCategoriesCommand $cmd): void
    {
        $this->categories->reorder($cmd->positions);
    }
}
