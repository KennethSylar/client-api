<?php

namespace App\Application\Orders\Commands;

final class LoginCustomerCommand
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {}
}
