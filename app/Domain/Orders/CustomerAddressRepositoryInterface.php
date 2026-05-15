<?php

namespace App\Domain\Orders;

interface CustomerAddressRepositoryInterface
{
    /** @return CustomerAddress[] */
    public function findByCustomer(int $customerId): array;

    public function findById(int $id, int $customerId): ?CustomerAddress;

    public function save(CustomerAddress $address): CustomerAddress;

    public function delete(int $id, int $customerId): void;

    public function setDefault(int $id, int $customerId): void;
}
