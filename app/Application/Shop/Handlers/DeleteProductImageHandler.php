<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\DeleteProductImageCommand;
use App\Domain\Shop\ProductRepositoryInterface;

final class DeleteProductImageHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(DeleteProductImageCommand $cmd): void
    {
        $images = $this->products->findImages($cmd->productId);
        $found  = false;
        foreach ($images as $img) {
            if ($img->id === $cmd->imageId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \DomainException('Image not found.');
        }

        $this->products->deleteImage($cmd->imageId, $cmd->productId);
    }
}
