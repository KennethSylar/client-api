<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Shop\StockRepositoryInterface;

class MySqlStockRepository extends AbstractMysqlRepository implements StockRepositoryInterface
{
    public function decrementProduct(int $productId, int $qty): void
    {
        $this->db->table('shop_products')
            ->where('id', $productId)
            ->set('stock_qty', "stock_qty - {$qty}", false)
            ->update();
    }

    public function decrementVariant(int $variantId, int $qty): void
    {
        $this->db->table('shop_product_variants')
            ->where('id', $variantId)
            ->set('stock_qty', "stock_qty - {$qty}", false)
            ->update();
    }

    public function incrementProduct(int $productId, int $qty): void
    {
        $this->db->table('shop_products')
            ->where('id', $productId)
            ->set('stock_qty', "stock_qty + {$qty}", false)
            ->update();
    }

    public function incrementVariant(int $variantId, int $qty): void
    {
        $this->db->table('shop_product_variants')
            ->where('id', $variantId)
            ->set('stock_qty', "stock_qty + {$qty}", false)
            ->update();
    }

    public function setProductQty(int $productId, int $qty): void
    {
        $this->db->table('shop_products')
            ->where('id', $productId)
            ->update(['stock_qty' => $qty]);
    }

    public function setVariantQty(int $variantId, int $qty): void
    {
        $this->db->table('shop_product_variants')
            ->where('id', $variantId)
            ->update(['stock_qty' => $qty]);
    }

    public function logAdjustment(
        int    $productId,
        ?int   $variantId,
        int    $delta,
        string $source,
        ?int   $referenceId,
        string $note,
        int    $qtyBefore,
        int    $qtyAfter,
    ): void {
        $this->db->table('shop_stock_adjustments')->insert([
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

    public function getHistory(int $productId, int $limit = 50): array
    {
        $rows = $this->db->table('shop_stock_adjustments a')
            ->select('a.*, v.name AS variant_name')
            ->join('shop_product_variants v', 'v.id = a.variant_id', 'left')
            ->where('a.product_id', $productId)
            ->orderBy('a.created_at', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        return array_map(function (array $row): array {
            return [
                'id'           => (int)  $row['id'],
                'product_id'   => (int)  $row['product_id'],
                'variant_id'   => $row['variant_id'] !== null ? (int) $row['variant_id'] : null,
                'variant_name' => $row['variant_name'],
                'delta'        => (int)  $row['delta'],
                'qty_before'   => (int)  $row['qty_before'],
                'qty_after'    => (int)  $row['qty_after'],
                'source'       => $row['source'],
                'reference_id' => $row['reference_id'] !== null ? (int) $row['reference_id'] : null,
                'note'         => $row['note'],
                'created_at'   => $row['created_at'],
            ];
        }, $rows);
    }
}
