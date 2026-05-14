<?php

namespace App\Application\Shop\Commands;

final class CreateCategoryCommand
{
    public function __construct(
        public readonly string $name,
        public readonly ?int   $parentId = null,
        public readonly int    $position = 0,
    ) {}
}
