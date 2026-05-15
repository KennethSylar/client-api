<?php

namespace App\Application\Shop\Queries;

final class GetProductQuery
{
    public function __construct(
        public readonly ?int    $id   = null,
        public readonly ?string $slug = null,
    ) {}
}
