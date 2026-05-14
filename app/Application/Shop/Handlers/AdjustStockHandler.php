<?php

namespace App\Application\Shop\Handlers;

use App\Application\Shop\Commands\AdjustStockCommand;
use App\Application\Ports\LowStockNotifierInterface;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Shop\StockRepositoryInterface;

final class AdjustStockHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface  $products,
        private readonly StockRepositoryInterface    $stock,
        private readonly LowStockNotifierInterface   $lowStockNotifier,
    ) {}

    /**
     * Returns ['qty_before' => int, 'qty_after' => int, 'delta' => int].
     */
    public function handle(AdjustStockCommand $cmd): array
    {
        $product = $this->products->findById($cmd->productId);
        if ($product === null) {
            throw new \DomainException('Product not found.');
        }

        if (!in_array($cmd->mode, ['set', 'adjust'], true)) {
            throw new \InvalidArgumentException("mode must be 'set' or 'adjust'.");
        }

        if ($cmd->variantId !== null) {
            return $this->adjustVariant($cmd, $product->id);
        }

        return $this->adjustProduct($cmd, $product);
    }

    private function adjustProduct(AdjustStockCommand $cmd, \App\Domain\Shop\Product $product): array
    {
        [$qtyBefore, $qtyAfter, $delta] = $this->compute($product->stockQty, $cmd);

        if ($qtyAfter < 0) {
            throw new \InvalidArgumentException('Stock cannot go below 0.');
        }

        $this->stock->setProductQty($cmd->productId, $qtyAfter);
        $this->stock->logAdjustment(
            $cmd->productId, null, $delta, 'manual', null,
            $cmd->note, $qtyBefore, $qtyAfter,
        );

        if ($delta < 0) {
            // Reload to get updated stockQty for the debounce check
            $updated = $this->products->findById($cmd->productId);
            if ($updated !== null) {
                $this->lowStockNotifier->notifyIfNeeded($updated);
            }
        }

        return ['product_id' => $cmd->productId, 'qty_before' => $qtyBefore, 'qty_after' => $qtyAfter, 'delta' => $delta];
    }

    private function adjustVariant(AdjustStockCommand $cmd, int $productId): array
    {
        $variant = $this->products->findVariantById($cmd->variantId, $productId);
        if ($variant === null) {
            throw new \DomainException('Variant not found on this product.');
        }

        [$qtyBefore, $qtyAfter, $delta] = $this->compute($variant->stockQty, $cmd);

        if ($qtyAfter < 0) {
            throw new \InvalidArgumentException('Stock cannot go below 0.');
        }

        $this->stock->setVariantQty($cmd->variantId, $qtyAfter);
        $this->stock->logAdjustment(
            $productId, $cmd->variantId, $delta, 'manual', null,
            $cmd->note, $qtyBefore, $qtyAfter,
        );

        if ($delta < 0) {
            $updated = $this->products->findById($productId);
            if ($updated !== null) {
                $this->lowStockNotifier->notifyIfNeeded($updated);
            }
        }

        return ['variant_id' => $cmd->variantId, 'qty_before' => $qtyBefore, 'qty_after' => $qtyAfter, 'delta' => $delta];
    }

    /** Returns [qtyBefore, qtyAfter, delta]. */
    private function compute(int $current, AdjustStockCommand $cmd): array
    {
        if ($cmd->mode === 'set') {
            if ($cmd->qty === null) {
                throw new \InvalidArgumentException("qty is required for mode 'set'.");
            }
            $qtyAfter = max(0, $cmd->qty);
            $delta    = $qtyAfter - $current;
            return [$current, $qtyAfter, $delta];
        }

        $qtyAfter = $current + $cmd->delta;
        return [$current, $qtyAfter, $cmd->delta];
    }
}
