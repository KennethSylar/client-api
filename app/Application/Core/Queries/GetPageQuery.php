<?php

namespace App\Application\Core\Queries;

final class GetPageQuery
{
    public function __construct(
        public readonly string $slug,
    ) {}
}
