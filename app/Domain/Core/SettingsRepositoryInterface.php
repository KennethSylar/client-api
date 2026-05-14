<?php

namespace App\Domain\Core;

interface SettingsRepositoryInterface
{
    public function get(string $key, string $default = ''): string;

    /** @return array<string,string> */
    public function getMany(array $keys): array;

    public function set(string $key, string $value): void;

    /** @param array<string,string> $keyValues */
    public function setMany(array $keyValues): void;
}
