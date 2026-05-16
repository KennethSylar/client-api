<?php

namespace App\Application\Core\Commands;

final class CreateAdminUserCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role,
    ) {}
}
