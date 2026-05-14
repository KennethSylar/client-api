<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\CreateCategoryCommand;
use App\Domain\Shop\Category;
use App\Domain\Shop\CategoryRepositoryInterface;

final class CreateCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function handle(CreateCategoryCommand $cmd): Category
    {
        $slug = $this->uniqueSlug($this->slugify($cmd->name));

        $category = new Category(
            id:       0,
            parentId: $cmd->parentId,
            slug:     $slug,
            name:     $cmd->name,
            position: $cmd->position,
        );

        return $this->categories->save($category);
    }

    private function slugify(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($text))), '-');
    }

    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug   = $base;
        $suffix = 2;
        while ($this->categories->slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }
}
