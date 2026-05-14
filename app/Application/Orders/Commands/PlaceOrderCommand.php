<?php

namespace App\Application\Orders\Commands;

use App\Application\Orders\DTOs\CartItemDTO;

final class PlaceOrderCommand
{
    /**
     * @param CartItemDTO[] $items
     */
    public function __construct(
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly string  $email,
        public readonly ?string $phone,
        public readonly string  $addressLine1,
        public readonly string  $addressLine2,
        public readonly string  $city,
        public readonly string  $province,
        public readonly string  $postalCode,
        public readonly string  $country,
        public readonly string  $gateway,
        public readonly array   $items,
        public readonly string  $notes        = '',
        public readonly ?int    $customerId   = null,
    ) {}
}
