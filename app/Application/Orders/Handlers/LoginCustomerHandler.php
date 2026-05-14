<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\LoginCustomerCommand;
use App\Domain\Orders\Customer;
use App\Domain\Orders\CustomerRepositoryInterface;

final class LoginCustomerHandler
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {}

    /**
     * Returns [Customer $customer, string $token].
     */
    public function handle(LoginCustomerCommand $cmd): array
    {
        $email    = strtolower(trim($cmd->email));
        $customer = $this->customers->findByEmail($email);
        $hash     = $customer ? $this->customers->getPasswordHash($customer->id) : null;

        if ($customer === null || $hash === null || !password_verify($cmd->password, $hash)) {
            throw new \InvalidArgumentException('Invalid credentials.');
        }

        $token = bin2hex(random_bytes(32));
        $this->customers->createSession(
            $customer->id,
            $token,
            new \DateTimeImmutable('+30 days'),
        );

        $this->customers->linkGuestOrders($customer->id, $email);

        return [$customer, $token];
    }
}
