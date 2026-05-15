<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\RefundOrderCommand;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\OrderStatusLogEntry;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Shop\StockRepositoryInterface;

final class RefundOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly StockRepositoryInterface   $stock,
    ) {}

    public function handle(RefundOrderCommand $cmd): void
    {
        $order = $this->orders->findById($cmd->orderId);
        if ($order === null) {
            throw new \DomainException('Order not found.');
        }

        if (!$order->isRefundable()) {
            throw new \DomainException('Order cannot be refunded in its current status.');
        }

        $this->orders->updateStatus($cmd->orderId, OrderStatus::Refunded);

        $this->orders->appendStatusLog(new OrderStatusLogEntry(
            orderId:    $cmd->orderId,
            fromStatus: $order->status->value,
            toStatus:   OrderStatus::Refunded->value,
            note:       $cmd->note ?: 'Manual refund by admin',
            createdAt:  new \DateTimeImmutable(),
        ));

        // Restore stock for each item — subtract qty already restored by prior partial refunds
        foreach ($order->items as $item) {
            $alreadyRefunded = $order->refundedQtyForItem($item->id);
            $restoreQty      = $item->qty - $alreadyRefunded;
            if ($restoreQty <= 0) continue;

            if ($item->variantId !== null) {
                $variant = $this->products->findVariantById($item->variantId, $item->productId ?? 0);
                if ($variant === null || !$variant->trackStock) continue;

                $qtyBefore = $variant->stockQty;
                $this->stock->incrementVariant($item->variantId, $restoreQty);
                $this->stock->logAdjustment(
                    $item->productId, $item->variantId, $restoreQty,
                    'refund', $cmd->orderId, '', $qtyBefore, $qtyBefore + $restoreQty,
                );
            } elseif ($item->productId !== null) {
                $product = $this->products->findById($item->productId);
                if ($product === null || !$product->trackStock) continue;

                $qtyBefore = $product->stockQty;
                $this->stock->incrementProduct($item->productId, $restoreQty);
                $this->stock->logAdjustment(
                    $item->productId, null, $restoreQty,
                    'refund', $cmd->orderId, '', $qtyBefore, $qtyBefore + $restoreQty,
                );
            }
        }
    }
}
