<?php

namespace App\Domain\Shop;

interface StockRepositoryInterface
{
    public function decrementProduct(int $productId, int $qty): void;

    public function decrementVariant(int $variantId, int $qty): void;

    public function incrementProduct(int $productId, int $qty): void;

    public function incrementVariant(int $variantId, int $qty): void;

    public function setProductQty(int $productId, int $qty): void;

    public function setVariantQty(int $variantId, int $qty): void;

    /**
     * @param string $source  'manual' | 'order' | 'refund' | 'import'
     */
    public function logAdjustment(
        int     $productId,
        ?int    $variantId,
        int     $delta,
        string  $source,
        ?int    $referenceId,
        string  $note,
        int     $qtyBefore,
        int     $qtyAfter,
    ): void;

    /** @return array[] */
    public function getHistory(int $productId, int $limit = 50): array;
}
