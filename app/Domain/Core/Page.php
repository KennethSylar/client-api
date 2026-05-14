<?php

namespace App\Domain\Core;

final class Page
{
    public function __construct(
        public readonly string             $slug,
        public readonly array              $data,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            slug:      $row['slug'],
            data:      is_string($row['data']) ? (json_decode($row['data'], true) ?? []) : ($row['data'] ?? []),
            updatedAt: new \DateTimeImmutable($row['updated_at'] ?? 'now'),
        );
    }
}
