<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\PartialRefundCommand;
use App\Domain\Orders\OrderRefund;
use App\Domain\Orders\OrderRefundItem;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\OrderStatusLogEntry;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Shop\StockRepositoryInterface;

final class PartialRefundHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly StockRepositoryInterface   $stock,
    ) {}

    public function handle(PartialRefundCommand $cmd): void
    {
        $order = $this->orders->findById($cmd->orderId);
        if ($order === null) {
            throw new \DomainException('Order not found.');
        }

        if (!$order->isRefundable()) {
            throw new \DomainException('Order cannot be refunded in its current status.');
        }

        if ($cmd->amountCents <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        if (empty($cmd->items)) {
            throw new \InvalidArgumentException('At least one item must be specified for a partial refund.');
        }

        // ── Validate each refund item ──────────────────────────────────
        $itemsById = [];
        foreach ($order->items as $item) {
            $itemsById[$item->id] = $item;
        }

        foreach ($cmd->items as $ri) {
            if (!isset($itemsById[$ri->orderItemId])) {
                throw new \DomainException("Order item #{$ri->orderItemId} not found in this order.");
            }
            $item            = $itemsById[$ri->orderItemId];
            $alreadyRefunded = $order->refundedQtyForItem($ri->orderItemId);
            $remaining       = $item->qty - $alreadyRefunded;

            if ($ri->qty <= 0 || $ri->qty > $remaining) {
                throw new \DomainException(
                    "Cannot refund {$ri->qty} of item #{$ri->orderItemId} — only {$remaining} remaining."
                );
            }
        }

        // ── Persist the refund record ──────────────────────────────────
        $refund = new OrderRefund(
            id:          0,
            orderId:     $cmd->orderId,
            amountCents: $cmd->amountCents,
            note:        $cmd->note ?: null,
            createdAt:   new \DateTimeImmutable(),
        );
        foreach ($cmd->items as $ri) {
            $refund->items[] = new OrderRefundItem(0, 0, $ri->orderItemId, $ri->qty);
        }

        $savedRefund = $this->orders->saveRefund($refund);

        // ── Determine new order status ─────────────────────────────────
        // Reload refunds to get full picture (including the one we just saved)
        $allRefunds = $this->orders->findRefundsByOrder($cmd->orderId);
        $isFullRefund = $this->isCompletelyRefunded($order->items, $allRefunds);

        $newStatus = $isFullRefund ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded;
        $this->orders->updateStatus($cmd->orderId, $newStatus);
        $this->orders->appendStatusLog(new OrderStatusLogEntry(
            orderId:    $cmd->orderId,
            fromStatus: $order->status->value,
            toStatus:   $newStatus->value,
            note:       $cmd->note ?: 'Partial refund by admin',
            createdAt:  new \DateTimeImmutable(),
        ));

        // ── Restore stock for refunded quantities ──────────────────────
        foreach ($cmd->items as $ri) {
            $item = $itemsById[$ri->orderItemId];

            if ($item->variantId !== null) {
                $variant = $this->products->findVariantById($item->variantId, $item->productId ?? 0);
                if ($variant === null || !$variant->trackStock) continue;

                $qtyBefore = $variant->stockQty;
                $this->stock->incrementVariant($item->variantId, $ri->qty);
                $this->stock->logAdjustment(
                    $item->productId, $item->variantId, $ri->qty,
                    'refund', $cmd->orderId, '', $qtyBefore, $qtyBefore + $ri->qty,
                );
            } elseif ($item->productId !== null) {
                $product = $this->products->findById($item->productId);
                if ($product === null || !$product->trackStock) continue;

                $qtyBefore = $product->stockQty;
                $this->stock->incrementProduct($item->productId, $ri->qty);
                $this->stock->logAdjustment(
                    $item->productId, null, $ri->qty,
                    'refund', $cmd->orderId, '', $qtyBefore, $qtyBefore + $ri->qty,
                );
            }
        }
    }

    private function isCompletelyRefunded(array $orderItems, array $allRefunds): bool
    {
        $refundedQtys = [];
        foreach ($allRefunds as $refund) {
            foreach ($refund->items as $ri) {
                $refundedQtys[$ri->orderItemId] = ($refundedQtys[$ri->orderItemId] ?? 0) + $ri->qty;
            }
        }

        foreach ($orderItems as $item) {
            $refunded = $refundedQtys[$item->id] ?? 0;
            if ($refunded < $item->qty) {
                return false;
            }
        }

        return true;
    }
}
