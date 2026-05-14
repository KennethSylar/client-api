<?php

namespace App\Domain\Core;

interface AdminSessionRepositoryInterface
{
    public function create(string $token, string $expiresAt): void;

    /** @return array{token:string,expires_at:string}|null */
    public function find(string $token): ?array;

    public function delete(string $token): void;

    public function deleteExpired(): void;
}
