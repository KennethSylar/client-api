<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\CreateProductCommand;
use App\Domain\Shop\Product;
use App\Domain\Shop\ProductRepositoryInterface;

final class CreateProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(CreateProductCommand $cmd): Product
    {
        if ($cmd->name === '') {
            throw new \InvalidArgumentException('name is required.');
        }
        if ($cmd->price < 0) {
            throw new \InvalidArgumentException('price must be >= 0.');
        }

        $base = $cmd->slug ? $this->slugify($cmd->slug) : $this->slugify($cmd->name);
        $slug = $this->uniqueSlug($base);

        $product = new Product(
            id:                0,
            categoryId:        $cmd->categoryId,
            slug:              $slug,
            name:              $cmd->name,
            description:       $cmd->description,
            price:             $cmd->price,
            vatExempt:         $cmd->vatExempt,
            trackStock:        $cmd->trackStock,
            stockQty:          $cmd->stockQty,
            lowStockThreshold: $cmd->lowStockThreshold,
            landingContent:    $cmd->landingContent,
            active:            $cmd->active,
        );

        return $this->products->save($product);
    }

    private function slugify(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($text))), '-');
    }

    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug   = $base;
        $suffix = 2;
        while ($this->products->slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }
}
