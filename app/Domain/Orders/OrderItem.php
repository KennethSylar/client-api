<?php

namespace App\Domain\Orders;

use App\Domain\Shared\Money;

final class OrderItem
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $orderId,
        public readonly ?int    $productId,
        public readonly ?int    $variantId,
        public readonly string  $productName,
        public readonly ?string $variantName,
        public readonly int     $qty,
        public readonly Money   $unitPrice,
        public readonly Money   $lineTotal,
        public readonly ?string $sku,
        public readonly ?string $productSlug,
        public readonly ?string $coverImage,
    ) {}

    public static function fromArray(array $row, string $currency = 'ZAR'): self
    {
        return new self(
            id:          (int) $row['id'],
            orderId:     (int) $row['order_id'],
            productId:   isset($row['product_id'])  ? (int) $row['product_id']  : null,
            variantId:   isset($row['variant_id'])  ? (int) $row['variant_id']  : null,
            productName:       $row['product_name'],
            variantName:       $row['variant_name'] ?? null,
            qty:         (int) $row['qty'],
            unitPrice:   Money::fromCents((int) $row['unit_price_cents'], $currency),
            lineTotal:   Money::fromCents((int) $row['line_total_cents'], $currency),
            sku:               $row['sku'] ?? null,
            productSlug:       $row['product_slug']       ?? null,
            coverImage:        $row['product_cover_image'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'order_id'         => $this->orderId,
            'product_id'       => $this->productId,
            'variant_id'       => $this->variantId,
            'product_name'     => $this->productName,
            'variant_name'     => $this->variantName,
            'qty'              => $this->qty,
            'unit_price_cents' => $this->unitPrice->amountCents,
            'line_total_cents' => $this->lineTotal->amountCents,
            'sku'              => $this->sku,
        ];
    }
}
