<?php

namespace App\Application\Orders\Queries;

final class GetOrderQuery
{
    public function __construct(
        public readonly ?int    $id    = null,
        public readonly ?string $token = null,
    ) {}
}
