<?php

namespace App\Domain\Orders;

final class OrderRefund
{
    /** @var OrderRefundItem[] */
    public array $items = [];

    public function __construct(
        public readonly int    $id,
        public readonly int    $orderId,
        public readonly int    $amountCents,
        public readonly ?string $note,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:          (int) $row['id'],
            orderId:     (int) $row['order_id'],
            amountCents: (int) $row['amount_cents'],
            note:              $row['note'] ?? null,
            createdAt:   new \DateTimeImmutable($row['created_at'] ?? 'now'),
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'order_id'     => $this->orderId,
            'amount_cents' => $this->amountCents,
            'note'         => $this->note,
            'created_at'   => $this->createdAt->format('Y-m-d H:i:s'),
            'items'        => array_map(fn($i) => $i->toArray(), $this->items),
        ];
    }
}
