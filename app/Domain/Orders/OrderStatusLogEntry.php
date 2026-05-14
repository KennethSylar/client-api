<?php

namespace App\Domain\Orders;

final class OrderStatusLogEntry
{
    public function __construct(
        public readonly int                $orderId,
        public readonly ?string            $fromStatus,
        public readonly string             $toStatus,
        public readonly ?string            $note,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            orderId:    (int) $row['order_id'],
            fromStatus:       $row['from_status'] ?? null,
            toStatus:         $row['to_status'],
            note:             $row['note']        ?? null,
            createdAt:  new \DateTimeImmutable($row['created_at'] ?? 'now'),
        );
    }

    public function toArray(): array
    {
        return [
            'from'       => $this->fromStatus,
            'to'         => $this->toStatus,
            'note'       => $this->note,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
