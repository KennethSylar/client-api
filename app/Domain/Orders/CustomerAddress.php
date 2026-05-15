<?php

namespace App\Domain\Orders;

final class CustomerAddress
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $customerId,
        public readonly ?string $label,
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly ?string $phone,
        public readonly string  $addressLine1,
        public readonly ?string $addressLine2,
        public readonly string  $city,
        public readonly ?string $province,
        public readonly string  $postalCode,
        public readonly string  $country,
        public readonly bool    $isDefault,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'label'         => $this->label,
            'first_name'    => $this->firstName,
            'last_name'     => $this->lastName,
            'phone'         => $this->phone,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'city'          => $this->city,
            'province'      => $this->province,
            'postal_code'   => $this->postalCode,
            'country'       => $this->country,
            'is_default'    => $this->isDefault,
            'created_at'    => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
