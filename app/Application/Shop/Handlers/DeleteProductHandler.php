<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\DeleteProductCommand;
use App\Domain\Shop\ProductRepositoryInterface;

final class DeleteProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(DeleteProductCommand $cmd): void
    {
        if ($this->products->findById($cmd->id) === null) {
            throw new \DomainException('Product not found.');
        }

        $this->products->delete($cmd->id);
    }
}
