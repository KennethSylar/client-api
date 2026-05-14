<?php

namespace App\Domain\Shop;

final class ProductVariant
{
    public function __construct(
        public readonly int    $id,
        public readonly int    $productId,
        public readonly string $name,
        public readonly float  $priceAdjustment,
        public readonly bool   $trackStock,
        public readonly int    $stockQty,
        public readonly int    $position,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:              (int)   $row['id'],
            productId:       (int)   $row['product_id'],
            name:                    $row['name'],
            priceAdjustment: (float) ($row['price_adjustment'] ?? 0),
            trackStock:      (bool)  ($row['track_stock']      ?? true),
            stockQty:        (int)   ($row['stock_qty']        ?? 0),
            position:        (int)   ($row['position']         ?? 0),
        );
    }

    public function inStock(): bool
    {
        return !$this->trackStock || $this->stockQty > 0;
    }

    public function effectivePrice(float $basePrice): float
    {
        return $basePrice + $this->priceAdjustment;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'product_id'       => $this->productId,
            'name'             => $this->name,
            'price_adjustment' => $this->priceAdjustment,
            'track_stock'      => $this->trackStock,
            'stock_qty'        => $this->stockQty,
            'position'         => $this->position,
        ];
    }
}
