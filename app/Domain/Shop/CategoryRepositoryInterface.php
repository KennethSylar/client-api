<?php

namespace App\Domain\Shop;

interface CategoryRepositoryInterface
{
    /** @return Category[] */
    public function findAll(): array;

    /**
     * Returns all categories with active product counts (for public shop listing).
     * @return array[] Each row: {id, parent_id, slug, name, position, product_count}
     */
    public function findAllWithProductCount(): array;

    public function findById(int $id): ?Category;

    public function save(Category $category): Category;

    public function delete(int $id): void;

    /** @param array<int,int> $positions  [id => position, ...] */
    public function reorder(array $positions): void;

    public function slugExists(string $slug, ?int $excludeId = null): bool;
}
