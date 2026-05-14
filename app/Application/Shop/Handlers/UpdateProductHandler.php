<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\UpdateProductCommand;
use App\Domain\Shop\Product;
use App\Domain\Shop\ProductRepositoryInterface;

final class UpdateProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function handle(UpdateProductCommand $cmd): Product
    {
        $existing = $this->products->findById($cmd->id);
        if ($existing === null) {
            throw new \DomainException('Product not found.');
        }

        $name = $cmd->name !== null ? trim($cmd->name) : $existing->name;
        if ($name === '') {
            throw new \InvalidArgumentException('name cannot be empty.');
        }

        $price = $cmd->price ?? $existing->price;
        if ($price < 0) {
            throw new \InvalidArgumentException('price must be >= 0.');
        }

        // Determine slug
        if ($cmd->slug !== null) {
            $slug = $this->uniqueSlug($this->slugify($cmd->slug), $cmd->id);
        } elseif ($cmd->name !== null && $cmd->name !== $existing->name) {
            $slug = $this->uniqueSlug($this->slugify($name), $cmd->id);
        } else {
            $slug = $existing->slug;
        }

        $product = new Product(
            id:                $cmd->id,
            categoryId:        $cmd->setCategoryId ? $cmd->categoryId : $existing->categoryId,
            slug:              $slug,
            name:              $name,
            description:       $cmd->description  ?? $existing->description,
            price:             $price,
            vatExempt:         $cmd->vatExempt    ?? $existing->vatExempt,
            trackStock:        $cmd->trackStock   ?? $existing->trackStock,
            stockQty:          $cmd->stockQty     ?? $existing->stockQty,
            lowStockThreshold: $cmd->lowStockThreshold ?? $existing->lowStockThreshold,
            landingContent:    $cmd->setLandingContent ? $cmd->landingContent : $existing->landingContent,
            active:            $cmd->active       ?? $existing->active,
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
