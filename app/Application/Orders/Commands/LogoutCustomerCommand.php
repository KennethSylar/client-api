<?php

namespace App\Application\Orders\Commands;

final class LogoutCustomerCommand
{
    public function __construct(
        public readonly string $token,
    ) {}
}
