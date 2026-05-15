<?php

namespace App\Domain\Orders;

final class OrderRefundItem
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $refundId,
        public readonly int     $orderItemId,
        public readonly int     $qty,
        public readonly ?string $productName = null,
        public readonly ?string $variantName = null,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:          (int) $row['id'],
            refundId:    (int) $row['refund_id'],
            orderItemId: (int) $row['order_item_id'],
            qty:         (int) $row['qty'],
            productName:       $row['product_name'] ?? null,
            variantName:       $row['variant_name'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'refund_id'     => $this->refundId,
            'order_item_id' => $this->orderItemId,
            'qty'           => $this->qty,
            'product_name'  => $this->productName,
            'variant_name'  => $this->variantName,
        ];
    }
}
