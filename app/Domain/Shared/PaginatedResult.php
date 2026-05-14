<?php

namespace App\Domain\Shared;

final class PaginatedResult
{
    public function __construct(
        public readonly array $items,
        public readonly int   $total,
        public readonly int   $page,
        public readonly int   $perPage,
    ) {}

    public function pages(): int
    {
        if ($this->perPage <= 0) return 1;
        return (int) ceil($this->total / $this->perPage);
    }

    public function meta(): array
    {
        return [
            'total'    => $this->total,
            'page'     => $this->page,
            'per_page' => $this->perPage,
            'pages'    => $this->pages(),
        ];
    }
}
