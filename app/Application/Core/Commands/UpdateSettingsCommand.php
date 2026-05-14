<?php

namespace App\Application\Core\Commands;

final class UpdateSettingsCommand
{
    /**
     * @param array<string,mixed> $keyValues Raw settings key-value pairs from request body.
     */
    public function __construct(
        public readonly array $keyValues,
    ) {}
}
