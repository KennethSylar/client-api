<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\UpdateAdminUserCommand;
use App\Domain\Core\AdminUserRepositoryInterface;

final class UpdateAdminUserHandler
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $users,
    ) {}

    public function handle(UpdateAdminUserCommand $cmd): void
    {
        $existing = $this->users->findById($cmd->id);
        if ($existing === null) {
            throw new \DomainException('User not found.');
        }

        // Check email uniqueness if changed
        if ($cmd->email !== $existing['email']) {
            $conflict = $this->users->findByEmail($cmd->email);
            if ($conflict !== null && $conflict['id'] !== $cmd->id) {
                throw new \DomainException('Email already in use.');
            }
        }

        // Cannot deactivate or demote the last active admin
        if (
            $existing['role'] === 'admin' &&
            ($cmd->role !== 'admin' || !$cmd->isActive) &&
            $this->users->countActiveAdmins() <= 1
        ) {
            throw new \DomainException('Cannot demote or deactivate the last admin.');
        }

        $data = [
            'name'      => $cmd->name,
            'email'     => $cmd->email,
            'role'      => $cmd->role,
            'is_active' => $cmd->isActive ? 1 : 0,
        ];

        if ($cmd->password !== null && $cmd->password !== '') {
            $data['password_hash'] = password_hash($cmd->password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->users->update($cmd->id, $data);
    }
}
