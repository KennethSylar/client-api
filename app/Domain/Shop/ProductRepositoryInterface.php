<?php

namespace App\Domain\Shop;

use App\Domain\Shared\PaginatedResult;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findBySlug(string $slug): ?Product;

    public function findAll(ProductFilter $filter): PaginatedResult;

    public function save(Product $product): Product;

    public function delete(int $id): void;

    public function slugExists(string $slug, ?int $excludeId = null): bool;

    public function stampLowStockAlert(int $productId): void;

    // ── Image sub-aggregate ───────────────────────────────────────────

    /** @return ProductImage[] */
    public function findImages(int $productId): array;

    public function addImage(ProductImage $image): ProductImage;

    public function deleteImage(int $imageId, int $productId): void;

    /** @param array<int,int> $positions [imageId => position, ...] */
    public function reorderImages(int $productId, array $positions): void;

    // ── Variant sub-aggregate ─────────────────────────────────────────

    /** @return ProductVariant[] */
    public function findVariants(int $productId): array;

    public function findVariantById(int $variantId, int $productId): ?ProductVariant;
}
