<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Infrastructure\Http\Controllers\BaseController;

/**
 * Shop\CartValidation  (public)
 *
 * POST /shop/cart/validate
 *
 * Accepts the client's cart and returns each line with:
 *   - current server price (detects price changes since item was added)
 *   - current stock status
 *   - adjusted qty if stock is now less than requested
 *
 * Called by the frontend before opening checkout to prevent
 * stale-price and oversell race conditions.
 */
class CartValidation extends BaseController
{
    public function check(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $body  = $this->jsonBody();
        $input = $body['items'] ?? [];

        if (!is_array($input) || empty($input)) {
            return $this->error('items array is required.', 400);
        }

        $db         = \Config\Database::connect();
        $results    = [];
        $hasIssues  = false;

        foreach ($input as $line) {
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $variantId = isset($line['variant_id']) && $line['variant_id'] !== null
                ? (int) $line['variant_id'] : null;
            $qtyRequested = max(1, (int) ($line['qty'] ?? 1));
            $clientPrice  = isset($line['price']) ? (float) $line['price'] : null;

            if ($productId === 0) continue;

            // Fetch current product
            $product = $db->table('shop_products')
                ->where('id', $productId)
                ->where('active', 1)
                ->get()->getRowArray();

            if (!$product) {
                $results[] = [
                    'product_id'      => $productId,
                    'variant_id'      => $variantId,
                    'name'            => $line['name'] ?? 'Unknown product',
                    'variant_name'    => null,
                    'price'           => 0.00,
                    'price_adjustment'=> 0.00,
                    'effective_price' => 0.00,
                    'qty_requested'   => $qtyRequested,
                    'qty_available'   => 0,
                    'qty_adjusted'    => 0,
                    'in_stock'        => false,
                    'stock_changed'   => true,
                    'price_changed'   => false,
                    'removed'         => true,
                ];
                $hasIssues = true;
                continue;
            }

            $price           = (float) $product['price'];
            $priceAdjustment = 0.00;
            $variantName     = null;
            $trackStock      = (bool) $product['track_stock'];
            $stockQty        = (int) $product['stock_qty'];

            // Fetch variant if specified
            if ($variantId !== null) {
                $variant = $db->table('shop_product_variants')
                    ->where('id', $variantId)
                    ->where('product_id', $productId)
                    ->get()->getRowArray();

                if ($variant) {
                    $priceAdjustment = (float) $variant['price_adjustment'];
                    $variantName     = $variant['name'];
                    if ((bool) $variant['track_stock']) {
                        $trackStock = true;
                        $stockQty   = (int) $variant['stock_qty'];
                    }
                }
            }

            $effectivePrice = $price + $priceAdjustment;

            // Determine available qty
            $qtyAvailable = $trackStock ? $stockQty : $qtyRequested;
            $qtyAdjusted  = min($qtyRequested, max(0, $qtyAvailable));
            $inStock      = $qtyAvailable > 0;

            $stockChanged = $trackStock && $qtyAdjusted < $qtyRequested;
            $priceChanged = $clientPrice !== null && abs($clientPrice - $effectivePrice) > 0.001;

            if ($stockChanged || $priceChanged) $hasIssues = true;

            $results[] = [
                'product_id'      => $productId,
                'variant_id'      => $variantId,
                'name'            => $product['name'],
                'variant_name'    => $variantName,
                'price'           => $price,
                'price_adjustment'=> $priceAdjustment,
                'effective_price' => $effectivePrice,
                'qty_requested'   => $qtyRequested,
                'qty_available'   => $qtyAvailable,
                'qty_adjusted'    => $qtyAdjusted,
                'in_stock'        => $inStock,
                'stock_changed'   => $stockChanged,
                'price_changed'   => $priceChanged,
                'removed'         => false,
            ];
        }

        return $this->ok([
            'items'      => $results,
            'has_issues' => $hasIssues,
        ]);
    }
}
