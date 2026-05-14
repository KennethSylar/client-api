<?php

namespace App\Domain\Orders;

enum OrderStatus: string
{
    case Pending    = 'pending';
    case Paid       = 'paid';
    case Processing = 'processing';
    case Shipped    = 'shipped';
    case Delivered  = 'delivered';
    case Cancelled  = 'cancelled';
    case Refunded   = 'refunded';

    /** Returns true when a transition from $this → $new is permitted. */
    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Pending    => in_array($new, [self::Paid, self::Cancelled], true),
            self::Paid       => in_array($new, [self::Processing, self::Shipped, self::Cancelled, self::Refunded], true),
            self::Processing => in_array($new, [self::Shipped, self::Cancelled, self::Refunded], true),
            self::Shipped    => in_array($new, [self::Delivered, self::Refunded], true),
            self::Delivered  => in_array($new, [self::Refunded], true),
            self::Cancelled,
            self::Refunded   => false,
        };
    }

    /** Statuses from which stock should be restored on cancel/refund. */
    public function isRefundable(): bool
    {
        return in_array($this, [self::Paid, self::Processing, self::Shipped, self::Delivered], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
