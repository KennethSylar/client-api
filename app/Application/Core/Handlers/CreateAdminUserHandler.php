<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\CreateAdminUserCommand;
use App\Domain\Core\AdminUserRepositoryInterface;

final class CreateAdminUserHandler
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $users,
    ) {}

    /** Returns the new user's ID. Throws on duplicate email. */
    public function handle(CreateAdminUserCommand $cmd): int
    {
        if ($this->users->findByEmail($cmd->email) !== null) {
            throw new \DomainException('Email already in use.');
        }

        $hash = password_hash($cmd->password, PASSWORD_BCRYPT, ['cost' => 12]);

        return $this->users->create($cmd->name, $cmd->email, $hash, $cmd->role);
    }
}
