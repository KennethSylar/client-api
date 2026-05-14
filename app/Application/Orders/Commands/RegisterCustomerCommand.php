<?php

namespace App\Application\Orders\Commands;

final class RegisterCustomerCommand
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $password,
    ) {}
}
