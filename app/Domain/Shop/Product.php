<?php

namespace App\Domain\Shop;

final class Product
{
    /** @var ProductImage[] */
    public array $images = [];

    /** @var ProductVariant[] */
    public array $variants = [];

    public function __construct(
        public readonly int     $id,
        public readonly ?int    $categoryId,
        public readonly string  $slug,
        public readonly string  $name,
        public readonly string  $description,
        public readonly float   $price,
        public readonly bool    $vatExempt,
        public readonly bool    $trackStock,
        public readonly int     $stockQty,
        public readonly int     $lowStockThreshold,
        public readonly ?array  $landingContent,
        public readonly bool    $active,
        public readonly ?string $categoryName   = null,
        public readonly ?string $categorySlug   = null,
        public readonly ?\DateTimeImmutable $lowStockAlertedAt = null,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:                (int)  $row['id'],
            categoryId:        isset($row['category_id']) ? (int) $row['category_id'] : null,
            slug:                     $row['slug'],
            name:                     $row['name'],
            description:              $row['description'] ?? '',
            price:             (float)($row['price'] ?? 0),
            vatExempt:         (bool) ($row['vat_exempt']          ?? false),
            trackStock:        (bool) ($row['track_stock']         ?? true),
            stockQty:          (int)  ($row['stock_qty']           ?? 0),
            lowStockThreshold: (int)  ($row['low_stock_threshold'] ?? 5),
            landingContent:    isset($row['landing_content'])
                ? (is_string($row['landing_content']) ? json_decode($row['landing_content'], true) : $row['landing_content'])
                : null,
            active:            (bool) ($row['active'] ?? true),
            categoryName:             $row['category_name']        ?? null,
            categorySlug:             $row['category_slug']        ?? null,
            lowStockAlertedAt: isset($row['low_stock_alerted_at'])
                ? new \DateTimeImmutable($row['low_stock_alerted_at'])
                : null,
        );
    }

    public function inStock(): bool
    {
        return !$this->trackStock || $this->stockQty > 0;
    }

    /** True when stock is low but not zero (triggers alert debounce check). */
    public function isLowStock(): bool
    {
        return $this->trackStock
            && $this->stockQty > 0
            && $this->stockQty <= $this->lowStockThreshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->trackStock && $this->stockQty <= 0;
    }

    /** Whether the low-stock alert should be sent (debounced to 24 hours). */
    public function needsLowStockAlert(): bool
    {
        if (!$this->isLowStock() && !$this->isOutOfStock()) return false;
        if ($this->lowStockAlertedAt === null) return true;

        $hoursSinceAlert = (time() - $this->lowStockAlertedAt->getTimestamp()) / 3600;
        return $hoursSinceAlert >= 24;
    }
}
