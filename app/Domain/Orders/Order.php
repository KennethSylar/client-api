<?php

namespace App\Domain\Orders;

use App\Domain\Shared\Address;
use App\Domain\Shared\Money;
use App\Domain\Shop\PaymentGateway;

final class Order
{
    /** @var OrderItem[] */
    public array $items = [];

    /** @var OrderStatusLogEntry[] */
    public array $statusLog = [];

    public function __construct(
        public readonly int            $id,
        public readonly string         $token,
        public readonly ?int           $customerId,
        public readonly string         $firstName,
        public readonly string         $lastName,
        public readonly string         $email,
        public readonly ?string        $phone,
        public readonly Address        $address,
        public readonly Money          $subtotal,
        public readonly Money          $vat,
        public readonly Money          $shipping,
        public readonly Money          $total,
        public readonly string         $currency,
        public readonly OrderStatus    $status,
        public readonly ?PaymentGateway $paymentGateway,
        public readonly ?string        $paymentReference,
        public readonly ?\DateTimeImmutable $paidAt,
        public readonly ?string        $notes,
        public readonly ?string        $trackingCarrier,
        public readonly ?string        $trackingNumber,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {}

    public static function fromArray(array $row): self
    {
        $currency = $row['currency'] ?? 'ZAR';

        return new self(
            id:               (int)   $row['id'],
            token:                    $row['token'],
            customerId:       isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            firstName:                $row['first_name'],
            lastName:                 $row['last_name'],
            email:                    $row['email'],
            phone:                    $row['phone']             ?? null,
            address:          Address::fromArray($row),
            subtotal:         Money::fromCents((int)($row['subtotal_cents']  ?? 0), $currency),
            vat:              Money::fromCents((int)($row['vat_cents']       ?? 0), $currency),
            shipping:         Money::fromCents((int)($row['shipping_cents']  ?? 0), $currency),
            total:            Money::fromCents((int)($row['total_cents']     ?? 0), $currency),
            currency:         $currency,
            status:           OrderStatus::from($row['status'] ?? 'pending'),
            paymentGateway:   isset($row['payment_gateway'])
                ? PaymentGateway::tryFrom($row['payment_gateway'])
                : null,
            paymentReference: $row['payment_reference'] ?? null,
            paidAt:           isset($row['paid_at'])
                ? new \DateTimeImmutable($row['paid_at'])
                : null,
            notes:            $row['notes']            ?? null,
            trackingCarrier:  $row['tracking_carrier'] ?? null,
            trackingNumber:   $row['tracking_number']  ?? null,
            createdAt:        new \DateTimeImmutable($row['created_at'] ?? 'now'),
            updatedAt:        isset($row['updated_at'])
                ? new \DateTimeImmutable($row['updated_at'])
                : null,
        );
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    public function isPaid(): bool
    {
        return $this->status !== OrderStatus::Pending && $this->status !== OrderStatus::Cancelled;
    }

    public function isRefundable(): bool
    {
        return $this->status->isRefundable();
    }
}
