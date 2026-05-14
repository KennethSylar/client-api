<?php

namespace App\Application\Core\Commands;

final class AdminLoginCommand
{
    public function __construct(
        public readonly string $password,
    ) {}
}
