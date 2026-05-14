<?php

namespace App\Domain\Shop;

final class ProductImage
{
    public function __construct(
        public readonly int    $id,
        public readonly int    $productId,
        public readonly string $url,
        public readonly string $alt,
        public readonly int    $position,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:        (int) $row['id'],
            productId: (int) $row['product_id'],
            url:       $row['url'],
            alt:       $row['alt'] ?? '',
            position:  (int) ($row['position'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'product_id' => $this->productId,
            'url'        => $this->url,
            'alt'        => $this->alt,
            'position'   => $this->position,
        ];
    }
}
