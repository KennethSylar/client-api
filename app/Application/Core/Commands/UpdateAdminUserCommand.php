<?php

namespace App\Application\Core\Commands;

final class UpdateAdminUserCommand
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $name,
        public readonly string  $email,
        public readonly string  $role,
        public readonly bool    $isActive,
        public readonly ?string $password = null,
    ) {}
}
