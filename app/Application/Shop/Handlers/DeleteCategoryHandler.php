<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\DeleteCategoryCommand;
use App\Domain\Shop\CategoryRepositoryInterface;

final class DeleteCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function handle(DeleteCategoryCommand $cmd): void
    {
        if ($this->categories->findById($cmd->id) === null) {
            throw new \DomainException('Category not found.');
        }

        $this->categories->delete($cmd->id);
    }
}
