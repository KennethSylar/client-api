<?php

namespace App\Controllers\Admin\Shop;

use App\Application\Shop\Commands\AdjustStockCommand;
use App\Application\Shop\Queries\GetStockHistoryQuery;
use App\Controllers\BaseController;

class Stock extends BaseController
{
    public function adjust(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        $body      = $this->jsonBody();
        $mode      = $body['mode'] ?? 'adjust';
        $variantId = isset($body['variant_id']) ? (int) $body['variant_id'] : null;
        $note      = trim($body['note'] ?? '');

        try {
            $result = service('adjustStockHandler')->handle(new AdjustStockCommand(
                productId: $productId,
                variantId: $variantId,
                mode:      $mode,
                delta:     isset($body['delta']) ? (int) $body['delta'] : 0,
                qty:       isset($body['qty'])   ? (int) $body['qty']   : null,
                note:      $note,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->ok($result);
    }

    public function history(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $adjustments = service('getStockHistoryHandler')->handle(new GetStockHistoryQuery(
                productId: $productId,
                limit:     100,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->ok(['adjustments' => $adjustments]);
    }

    /**
     * @deprecated Use service('stockRepository')->logAdjustment() instead.
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
