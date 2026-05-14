<?php

namespace App\Controllers\Admin\Shop;

use App\Controllers\BaseController;
use App\Services\LowStockMailer;

/**
 * Admin\Shop\Stock  (protected)
 *
 * POST /admin/shop/products/:id/stock-adjustment  — manual stock adjustment
 * GET  /admin/shop/products/:id/stock-history     — adjustment log
 */
class Stock extends BaseController
{
    /**
     * POST /admin/shop/products/:id/stock-adjustment
     *
     * Body (set mode):   { "mode": "set",    "qty": 50,  "note": "Stocktake" }
     * Body (delta mode): { "mode": "adjust", "delta": -3, "note": "Damaged goods" }
     *                    delta > 0 = add stock, delta < 0 = remove stock
     *
     * Optional: "variant_id" to adjust a specific variant's stock instead.
     */
    public function adjust(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        $db      = \Config\Database::connect();
        $product = $db->table('shop_products')->where('id', $productId)->get()->getRowArray();

        if (!$product) {
            return $this->notFound('Product not found.');
        }

        $body      = $this->jsonBody();
        $mode      = $body['mode'] ?? 'adjust';   // 'set' | 'adjust'
        $variantId = isset($body['variant_id']) ? (int) $body['variant_id'] : null;
        $note      = trim($body['note'] ?? '');

        if (!in_array($mode, ['set', 'adjust'])) {
            return $this->error("mode must be 'set' or 'adjust'.", 400);
        }

        // ── Variant path ────────────────────────────────────────────
        if ($variantId !== null) {
            $variant = $db->table('shop_product_variants')
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->get()->getRowArray();

            if (!$variant) {
                return $this->notFound('Variant not found on this product.');
            }

            [$qtyBefore, $qtyAfter, $delta] = $this->computeQty(
                (int) $variant['stock_qty'], $mode, $body
            );
            if ($qtyBefore === null) {
                return $this->error("qty is required for mode 'set'.", 400);
            }
            if ($qtyAfter < 0) {
                return $this->error('Stock cannot go below 0.', 400);
            }

            $db->table('shop_product_variants')
               ->where('id', $variantId)
               ->update(['stock_qty' => $qtyAfter]);

            $this->logAdjustment($db, $productId, $variantId, $delta, 'manual', null, $note, $qtyBefore, $qtyAfter);

            if ($delta < 0) {
                LowStockMailer::checkAndSend($db, $productId);
            }

            return $this->ok([
                'variant_id' => $variantId,
                'qty_before' => $qtyBefore,
                'qty_after'  => $qtyAfter,
                'delta'      => $delta,
            ]);
        }

        // ── Product path ─────────────────────────────────────────────
        [$qtyBefore, $qtyAfter, $delta] = $this->computeQty(
            (int) $product['stock_qty'], $mode, $body
        );
        if ($qtyBefore === null) {
            return $this->error("qty is required for mode 'set'.", 400);
        }
        if ($qtyAfter < 0) {
            return $this->error('Stock cannot go below 0.', 400);
        }

        $db->table('shop_products')
           ->where('id', $productId)
           ->update(['stock_qty' => $qtyAfter]);

        $this->logAdjustment($db, $productId, null, $delta, 'manual', null, $note, $qtyBefore, $qtyAfter);

        if ($delta < 0) {
            LowStockMailer::checkAndSend($db, $productId);
        }

        return $this->ok([
            'product_id' => $productId,
            'qty_before' => $qtyBefore,
            'qty_after'  => $qtyAfter,
            'delta'      => $delta,
        ]);
    }

    /**
     * GET /admin/shop/products/:id/stock-history
     * Returns the 100 most recent adjustments for this product (all variants included).
     */
    public function history(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();

        if (!$db->table('shop_products')->where('id', $productId)->countAllResults()) {
            return $this->notFound('Product not found.');
        }

        $rows = $db->table('shop_stock_adjustments a')
            ->select('a.*, v.name AS variant_name')
            ->join('shop_product_variants v', 'v.id = a.variant_id', 'left')
            ->where('a.product_id', $productId)
            ->orderBy('a.created_at', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit(100)
            ->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['id']           = (int)  $row['id'];
            $row['product_id']   = (int)  $row['product_id'];
            $row['variant_id']   = $row['variant_id'] !== null ? (int) $row['variant_id'] : null;
            $row['reference_id'] = $row['reference_id'] !== null ? (int) $row['reference_id'] : null;
            $row['delta']        = (int)  $row['delta'];
            $row['qty_before']   = (int)  $row['qty_before'];
            $row['qty_after']    = (int)  $row['qty_after'];
        }

        return $this->ok(['adjustments' => $rows]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Compute [qtyBefore, qtyAfter, delta] from request body.
     * Returns [null, null, null] if required fields are missing.
     */
    private function computeQty(int $current, string $mode, array $body): array
    {
        if ($mode === 'set') {
            if (!isset($body['qty'])) return [null, null, null];
            $qtyAfter  = max(0, (int) $body['qty']);
            $delta     = $qtyAfter - $current;
            return [$current, $qtyAfter, $delta];
        }

        // mode = adjust
        $delta    = (int) ($body['delta'] ?? 0);
        $qtyAfter = $current + $delta;
        return [$current, $qtyAfter, $delta];
    }

    /**
     * Insert a row into shop_stock_adjustments.
     * Called from this controller and reused by order/refund flows (M5/M6).
     */
    public static function logAdjustment(
        \CodeIgniter\Database\BaseConnection $db,
        int     $productId,
        ?int    $variantId,
        int     $delta,
        string  $source      = 'manual',
        ?int    $referenceId = null,
        string  $note        = '',
        int     $qtyBefore   = 0,
        int     $qtyAfter    = 0
    ): void {
        $db->table('shop_stock_adjustments')->insert([
            'product_id'   => $productId,
            'variant_id'   => $variantId,
            'delta'        => $delta,
            'qty_before'   => $qtyBefore,
            'qty_after'    => $qtyAfter,
            'source'       => $source,
            'reference_id' => $referenceId,
            'note'         => $note,
        ]);
    }
}
