<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\UpdateSettingsCommand;
use App\Domain\Core\SettingsRepositoryInterface;

final class UpdateSettingsHandler
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
    ) {}

    public function handle(UpdateSettingsCommand $cmd): void
    {
        $normalised = [];

        foreach ($cmd->keyValues as $key => $value) {
            // Plain-text password → bcrypt hash
            if ($key === 'admin_password_hash' && !empty($value)) {
                $value = password_hash((string) $value, PASSWORD_BCRYPT);
            }

            // Accreditations array → JSON string
            if ($key === 'accreditations' && is_array($value)) {
                $value = json_encode($value);
            }

            $normalised[$key] = (string) $value;
        }

        $this->settings->setMany($normalised);
    }
}
