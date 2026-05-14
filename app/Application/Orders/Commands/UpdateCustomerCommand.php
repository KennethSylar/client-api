<?php

namespace App\Application\Orders\Commands;

final class UpdateCustomerCommand
{
    public function __construct(
        public readonly int     $customerId,
        public readonly ?string $firstName       = null,
        public readonly ?string $lastName        = null,
        public readonly ?string $phone           = null,
        public readonly ?string $newPassword     = null,
        public readonly ?string $currentPassword = null,
    ) {}
}
