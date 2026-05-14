<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\CancelOrderCommand;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\OrderStatusLogEntry;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Shop\StockRepositoryInterface;

final class CancelOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly StockRepositoryInterface   $stock,
    ) {}

    public function handle(CancelOrderCommand $cmd): void
    {
        $order = $this->orders->findById($cmd->orderId);
        if ($order === null || $order->status !== OrderStatus::Pending) {
            return; // idempotent
        }

        $this->orders->updateStatus($cmd->orderId, OrderStatus::Cancelled);

        $this->orders->appendStatusLog(new OrderStatusLogEntry(
            orderId:    $cmd->orderId,
            fromStatus: OrderStatus::Pending->value,
            toStatus:   OrderStatus::Cancelled->value,
            note:       $cmd->note ?: null,
            createdAt:  new \DateTimeImmutable(),
        ));

        // Restore stock
        foreach ($order->items as $item) {
            if ($item->variantId !== null) {
                $variant = $this->products->findVariantById($item->variantId, $item->productId ?? 0);
                if ($variant === null || !$variant->trackStock) continue;

                $qtyBefore = $variant->stockQty;
                $qtyAfter  = $qtyBefore + $item->qty;
                $this->stock->incrementVariant($item->variantId, $item->qty);
                $this->stock->logAdjustment(
                    $item->productId, $item->variantId, $item->qty,
                    'refund', $cmd->orderId, '', $qtyBefore, $qtyAfter,
                );
            } elseif ($item->productId !== null) {
                $product = $this->products->findById($item->productId);
                if ($product === null || !$product->trackStock) continue;

                $qtyBefore = $product->stockQty;
                $qtyAfter  = $qtyBefore + $item->qty;
                $this->stock->incrementProduct($item->productId, $item->qty);
                $this->stock->logAdjustment(
                    $item->productId, null, $item->qty,
                    'refund', $cmd->orderId, '', $qtyBefore, $qtyAfter,
                );
            }
        }
    }
}
