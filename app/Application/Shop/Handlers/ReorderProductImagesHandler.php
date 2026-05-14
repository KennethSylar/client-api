<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\ReorderProductImagesCommand;
use App\Domain\Shop\ProductImage;
use App\Domain\Shop\ProductRepositoryInterface;

final class ReorderProductImagesHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    /**
     * @return ProductImage[]
     */
    public function handle(ReorderProductImagesCommand $cmd): array
    {
        if ($this->products->findById($cmd->productId) === null) {
            throw new \DomainException('Product not found.');
        }

        $this->products->reorderImages($cmd->productId, $cmd->positions);

        return $this->products->findImages($cmd->productId);
    }
}
