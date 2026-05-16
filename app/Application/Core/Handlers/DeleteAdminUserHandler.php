<?php

namespace App\Application\Core\Handlers;

use App\Domain\Core\AdminUserRepositoryInterface;

final class DeleteAdminUserHandler
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $users,
    ) {}

    public function handle(int $targetId, int $requestingUserId): void
    {
        if ($targetId === $requestingUserId) {
            throw new \DomainException('Cannot delete your own account.');
        }

        $user = $this->users->findById($targetId);
        if ($user === null) {
            throw new \DomainException('User not found.');
        }

        if ($user['role'] === 'admin' && $this->users->countActiveAdmins() <= 1) {
            throw new \DomainException('Cannot delete the last admin user.');
        }

        $this->users->delete($targetId);
    }
}
