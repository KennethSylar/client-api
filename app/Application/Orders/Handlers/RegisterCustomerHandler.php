<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\RegisterCustomerCommand;
use App\Domain\Orders\Customer;
use App\Domain\Orders\CustomerRepositoryInterface;

final class RegisterCustomerHandler
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {}

    /**
     * Returns [Customer $customer, string $token].
     */
    public function handle(RegisterCustomerCommand $cmd): array
    {
        if (strlen($cmd->password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }

        $email = strtolower(trim($cmd->email));

        if ($this->customers->findByEmail($email) !== null) {
            throw new \DomainException('An account with this email already exists.');
        }

        $customer = new Customer(
            id:            0,
            email:         $email,
            firstName:     trim($cmd->firstName),
            lastName:      trim($cmd->lastName),
            phone:         null,
            emailVerified: false,
        );

        $passwordHash = password_hash($cmd->password, PASSWORD_BCRYPT);
        $saved        = $this->customers->save($customer, $passwordHash);

        $token = bin2hex(random_bytes(32));
        $this->customers->createSession(
            $saved->id,
            $token,
            new \DateTimeImmutable('+30 days'),
        );

        $this->customers->linkGuestOrders($saved->id, $email);

        return [$saved, $token];
    }
}
