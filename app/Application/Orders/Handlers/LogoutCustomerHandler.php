<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\LogoutCustomerCommand;
use App\Domain\Orders\CustomerRepositoryInterface;

final class LogoutCustomerHandler
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {}

    public function handle(LogoutCustomerCommand $cmd): void
    {
        $this->customers->deleteSession($cmd->token);
    }
}
