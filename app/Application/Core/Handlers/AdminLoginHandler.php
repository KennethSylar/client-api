<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\AdminLoginCommand;
use App\Domain\Core\AdminSessionRepositoryInterface;
use App\Domain\Core\AdminUserRepositoryInterface;

final class AdminLoginHandler
{
    public function __construct(
        private readonly AdminUserRepositoryInterface    $users,
        private readonly AdminSessionRepositoryInterface $sessions,
    ) {}

    /**
     * Verifies email + password and creates a session.
     * Returns ['token' => string, 'role' => string, 'name' => string].
     * Throws \InvalidArgumentException on bad credentials or inactive account.
     */
    public function handle(AdminLoginCommand $cmd): array
    {
        $user = $this->users->findByEmail($cmd->email);

        // Same error message for wrong email vs wrong password — prevents enumeration
        if ($user === null || !password_verify($cmd->password, $user['password_hash'])) {
            throw new \InvalidArgumentException('Invalid credentials.');
        }

        if (!(bool) $user['is_active']) {
            throw new \RuntimeException('Account disabled.');
        }

        $this->sessions->deleteExpired();

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $this->sessions->create($token, $expiresAt, (int) $user['id'], $user['role']);

        return [
            'token' => $token,
            'role'  => $user['role'],
            'name'  => $user['name'],
        ];
    }
}
