<?php

namespace App\Domain\Shop;

final class Category
{
    public function __construct(
        public readonly int     $id,
        public readonly ?int    $parentId,
        public readonly string  $slug,
        public readonly string  $name,
        public readonly int     $position,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:       (int) $row['id'],
            parentId: isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            slug:     $row['slug'],
            name:     $row['name'],
            position: (int) ($row['position'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'parent_id' => $this->parentId,
            'slug'      => $this->slug,
            'name'      => $this->name,
            'position'  => $this->position,
        ];
    }
}
