<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\AddProductImageCommand;
use App\Domain\Shop\ProductImage;
use App\Domain\Shop\ProductRepositoryInterface;

final class AddProductImageHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(AddProductImageCommand $cmd): ProductImage
    {
        if ($this->products->findById($cmd->productId) === null) {
            throw new \DomainException('Product not found.');
        }

        if (!filter_var($cmd->url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('url must be a valid URL.');
        }

        // If no position supplied the repo will append after the last image
        $image = new ProductImage(
            id:        0,
            productId: $cmd->productId,
            url:       $cmd->url,
            alt:       $cmd->alt,
            position:  $cmd->position ?? -1,
        );

        return $this->products->addImage($image);
    }
}
