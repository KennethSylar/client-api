<?php

namespace App\Domain\Shop;

final class Review
{
    public function __construct(
        public readonly int           $id,
        public readonly int           $productId,
        public readonly int           $customerId,
        public readonly int           $orderId,
        public readonly int           $rating,
        public readonly string        $title,
        public readonly ?string       $body,
        public readonly ReviewStatus  $status,
        public readonly ?string       $adminNote,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        // Denormalised — populated by repository joins
        public readonly ?string       $customerName = null,
        public readonly ?string       $productName  = null,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:           (int) $row['id'],
            productId:    (int) $row['product_id'],
            customerId:   (int) $row['customer_id'],
            orderId:      (int) $row['order_id'],
            rating:       (int) $row['rating'],
            title:              $row['title']      ?? '',
            body:               $row['body']       ?? null,
            status:       ReviewStatus::from($row['status'] ?? 'pending'),
            adminNote:          $row['admin_note'] ?? null,
            createdAt:    new \DateTimeImmutable($row['created_at'] ?? 'now'),
            updatedAt:    new \DateTimeImmutable($row['updated_at'] ?? 'now'),
            customerName:       $row['customer_name'] ?? null,
            productName:        $row['product_name']  ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'product_id'    => $this->productId,
            'customer_id'   => $this->customerId,
            'order_id'      => $this->orderId,
            'rating'        => $this->rating,
            'title'         => $this->title,
            'body'          => $this->body,
            'status'        => $this->status->value,
            'admin_note'    => $this->adminNote,
            'created_at'    => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'    => $this->updatedAt->format('Y-m-d H:i:s'),
            'customer_name' => $this->customerName,
            'product_name'  => $this->productName,
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === ReviewStatus::Approved;
    }
}
