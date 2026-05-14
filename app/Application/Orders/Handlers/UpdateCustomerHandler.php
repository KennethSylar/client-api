<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\UpdateCustomerCommand;
use App\Domain\Orders\Customer;
use App\Domain\Orders\CustomerRepositoryInterface;

final class UpdateCustomerHandler
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {}

    public function handle(UpdateCustomerCommand $cmd): Customer
    {
        $existing = $this->customers->findById($cmd->customerId);
        if ($existing === null) {
            throw new \DomainException('Customer not found.');
        }

        $passwordHash = null;

        if ($cmd->newPassword !== null) {
            if (strlen($cmd->newPassword) < 8) {
                throw new \InvalidArgumentException('Password must be at least 8 characters.');
            }
            if (empty($cmd->currentPassword)) {
                throw new \InvalidArgumentException('Current password is required to change password.');
            }
            $currentHash = $this->customers->getPasswordHash($cmd->customerId);
            if (!password_verify($cmd->currentPassword, $currentHash ?? '')) {
                throw new \InvalidArgumentException('Current password is incorrect.');
            }
            $passwordHash = password_hash($cmd->newPassword, PASSWORD_BCRYPT);
        }

        $updated = new Customer(
            id:            $existing->id,
            email:         $existing->email,
            firstName:     $cmd->firstName !== null ? trim($cmd->firstName) : $existing->firstName,
            lastName:      $cmd->lastName  !== null ? trim($cmd->lastName)  : $existing->lastName,
            phone:         $cmd->phone     !== null ? trim($cmd->phone)     : $existing->phone,
            emailVerified: $existing->emailVerified,
        );

        return $this->customers->save($updated, $passwordHash);
    }
}
