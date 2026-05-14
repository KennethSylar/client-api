<?php

namespace App\Domain\Core;

final class Setting
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
    ) {}
}
