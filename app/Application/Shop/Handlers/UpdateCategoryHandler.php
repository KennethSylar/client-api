<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\UpdateCategoryCommand;
use App\Domain\Shop\Category;
use App\Domain\Shop\CategoryRepositoryInterface;

final class UpdateCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function handle(UpdateCategoryCommand $cmd): Category
    {
        $existing = $this->categories->findById($cmd->id);
        if ($existing === null) {
            throw new \DomainException('Category not found.');
        }

        $name = $cmd->name !== null ? trim($cmd->name) : $existing->name;
        if ($name === '') {
            throw new \InvalidArgumentException('name cannot be empty.');
        }

        // Re-slug only when name changes
        $slug = ($cmd->name !== null && $cmd->name !== $existing->name)
            ? $this->uniqueSlug($this->slugify($name), $cmd->id)
            : $existing->slug;

        if ($cmd->setParent && $cmd->parentId === $cmd->id) {
            throw new \InvalidArgumentException('A category cannot be its own parent.');
        }

        $parentId = $cmd->setParent ? $cmd->parentId : $existing->parentId;
        $position = $cmd->position ?? $existing->position;

        return $this->categories->save(new Category(
            id:       $cmd->id,
            parentId: $parentId,
            slug:     $slug,
            name:     $name,
            position: $position,
        ));
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
