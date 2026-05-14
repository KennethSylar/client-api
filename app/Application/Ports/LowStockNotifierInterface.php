<?php

namespace App\Application\Ports;

use App\Domain\Shop\Product;

interface LowStockNotifierInterface
{
    /**
     * Sends a low-stock alert for $product if needed.
     * Implementations must respect the 24-hour debounce via Product::needsLowStockAlert().
     */
    public function notifyIfNeeded(Product $product): void;
}
