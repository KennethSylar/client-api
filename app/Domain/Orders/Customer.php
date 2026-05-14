<?php

namespace App\Domain\Orders;

final class Customer
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $email,
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly ?string $phone,
        public readonly bool    $emailVerified,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:            (int)  $row['id'],
            email:                $row['email'],
            firstName:            $row['first_name'],
            lastName:             $row['last_name'],
            phone:                $row['phone']           ?? null,
            emailVerified: (bool)($row['email_verified'] ?? false),
        );
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }
}
