<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\AdminLoginCommand;
use App\Domain\Core\AdminSessionRepositoryInterface;
use App\Domain\Core\SettingsRepositoryInterface;

final class AdminLoginHandler
{
    public function __construct(
        private readonly SettingsRepositoryInterface    $settings,
        private readonly AdminSessionRepositoryInterface $sessions,
    ) {}

    /**
     * Verifies the admin password and creates a session.
     * Returns the raw session token on success.
     * Throws \InvalidArgumentException on bad credentials.
     */
    public function handle(AdminLoginCommand $cmd): string
    {
        $hash = $this->settings->get('admin_password_hash');

        // bcryptjs produces $2b$ prefix; PHP password_verify requires $2y$
        $normalised = str_starts_with($hash, '$2b$')
            ? '$2y$' . substr($hash, 4)
            : $hash;

        if ($hash === '' || !password_verify($cmd->password, $normalised)) {
            throw new \InvalidArgumentException('Invalid password.');
        }

        $this->sessions->deleteExpired();

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $this->sessions->create($token, $expiresAt);

        return $token;
    }
}
