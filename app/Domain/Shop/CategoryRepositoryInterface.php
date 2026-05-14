<?php

namespace App\Domain\Shop;

interface CategoryRepositoryInterface
{
    /** @return Category[] */
    public function findAll(): array;

    public function findById(int $id): ?Category;

    public function save(Category $category): Category;

    public function delete(int $id): void;

    /** @param array<int,int> $positions  [id => position, ...] */
    public function reorder(array $positions): void;

    public function slugExists(string $slug, ?int $excludeId = null): bool;
}
