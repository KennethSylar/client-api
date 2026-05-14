<?php

namespace App\Application\Shop\Commands;

final class DeleteProductCommand
{
    public function __construct(
        public readonly int $id,
    ) {}
}
