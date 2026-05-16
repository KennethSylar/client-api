<?php

namespace App\Domain\Core;

interface AdminUserRepositoryInterface
{
    /** @return array{id:int,name:string,email:string,password_hash:string,role:string,is_active:int,created_at:string,updated_at:string}|null */
    public function findByEmail(string $email): ?array;

    /** @return array{id:int,name:string,email:string,role:string,is_active:int,created_at:string}|null */
    public function findById(int $id): ?array;

    /** @return list<array{id:int,name:string,email:string,role:string,is_active:int,created_at:string}> */
    public function list(): array;

    /** Returns the new user's ID. */
    public function create(string $name, string $email, string $passwordHash, string $role): int;

    public function update(int $id, array $data): void;

    public function delete(int $id): void;

    public function countActiveAdmins(): int;
}
