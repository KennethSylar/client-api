<?php

namespace App\Application\Shop\Commands;

final class DeleteCategoryCommand
{
    public function __construct(
        public readonly int $id,
    ) {}
}
