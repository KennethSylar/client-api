<?php

namespace App\Domain\Shared;

final class Address
{
    public function __construct(
        public readonly string  $line1,
        public readonly ?string $line2,
        public readonly string  $city,
        public readonly ?string $province,
        public readonly string  $postalCode,
        public readonly string  $country,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            line1:      $data['address_line1']  ?? $data['line1']      ?? '',
            line2:      $data['address_line2']  ?? $data['line2']      ?? null,
            city:       $data['city']           ?? '',
            province:   $data['province']       ?? null,
            postalCode: $data['postal_code']    ?? $data['postalCode'] ?? '',
            country:    $data['country']        ?? 'ZA',
        );
    }

    public function toArray(): array
    {
        return [
            'address_line1' => $this->line1,
            'address_line2' => $this->line2,
            'city'          => $this->city,
            'province'      => $this->province,
            'postal_code'   => $this->postalCode,
            'country'       => $this->country,
        ];
    }
}
