<?php

namespace App\Domain\Core;

interface PageRepositoryInterface
{
    /** @return Page[] */
    public function findAll(): array;

    public function findBySlug(string $slug): ?Page;

    public function save(Page $page): void;

    public function delete(string $slug): void;
}
