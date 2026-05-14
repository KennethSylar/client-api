<?php

namespace App\Domain\Orders;

interface CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer;

    public function findByEmail(string $email): ?Customer;

    public function findByToken(string $token): ?Customer;

    /** Insert or update. Returns the customer with its ID populated. */
    public function save(Customer $customer, ?string $passwordHash = null): Customer;

    public function getPasswordHash(int $customerId): ?string;

    public function createSession(int $customerId, string $token, \DateTimeImmutable $expiresAt): void;

    /** @return array{customer_id:int,expires_at:string}|null */
    public function findSession(string $token): ?array;

    public function deleteSession(string $token): void;

    /** Links all guest orders with $email to $customerId on first login. */
    public function linkGuestOrders(int $customerId, string $email): void;
}
