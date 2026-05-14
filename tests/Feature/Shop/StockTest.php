<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   POST /admin/shop/products/:id/stock-adjustment
 *   GET  /admin/shop/products/:id/stock-history
 */
class StockTest extends FeatureTestCase
{
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_products')->insert([
            'slug' => 'test-product', 'name' => 'Test Product', 'price' => 10,
            'track_stock' => 1, 'stock_qty' => 20, 'active' => 1,
        ]);
        $this->productId = (int) $db->insertID();
    }

    private function currentStock(): int
    {
        $db  = \Config\Database::connect($this->DBGroup);
        $row = $db->table('shop_products')->where('id', $this->productId)->get()->getRowArray();
        return (int) $row['stock_qty'];
    }

    // ── POST /admin/shop/products/:id/stock-adjustment ──────────────

    public function test_adjust_requires_auth(): void
    {
        $this->post("admin/shop/products/{$this->productId}/stock-adjustment", ['mode' => 'adjust', 'delta' => 5])
            ->assertStatus(401);
    }

    public function test_adjust_returns_404_for_unknown_product(): void
    {
        $this->withAdmin()->post('admin/shop/products/9999/stock-adjustment', ['mode' => 'adjust', 'delta' => 5])
            ->assertStatus(404);
    }

    public function test_adjust_rejects_invalid_mode(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode' => 'invalid',
        ]);
        $result->assertStatus(400);
    }

    public function test_adjust_mode_set_sets_exact_qty(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode' => 'set',
            'qty'  => 50,
            'note' => 'Stocktake',
        ]);
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertSame(20, $body['qty_before']);
        $this->assertSame(50, $body['qty_after']);
        $this->assertSame(30, $body['delta']);
        $this->assertSame(50, $this->currentStock());
    }

    public function test_adjust_mode_set_requires_qty(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode' => 'set',
        ]);
        $result->assertStatus(400);
    }

    public function test_adjust_mode_delta_adds_stock(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode'  => 'adjust',
            'delta' => 5,
        ]);
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertSame(20, $body['qty_before']);
        $this->assertSame(25, $body['qty_after']);
        $this->assertSame(5,  $body['delta']);
        $this->assertSame(25, $this->currentStock());
    }

    public function test_adjust_mode_delta_removes_stock(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode'  => 'adjust',
            'delta' => -8,
            'note'  => 'Damaged goods',
        ]);
        $result->assertStatus(200);
        $this->assertSame(12, $this->currentStock());
    }

    public function test_adjust_prevents_negative_stock(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode'  => 'adjust',
            'delta' => -999,
        ]);
        $result->assertStatus(400);
        $this->assertSame(20, $this->currentStock()); // unchanged
    }

    public function test_adjust_set_to_zero_is_allowed(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode' => 'set',
            'qty'  => 0,
        ]);
        $result->assertStatus(200);
        $this->assertSame(0, $this->currentStock());
    }

    public function test_adjust_logs_to_stock_history(): void
    {
        $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode'  => 'adjust',
            'delta' => -3,
            'note'  => 'Sold offline',
        ]);

        $db  = \Config\Database::connect($this->DBGroup);
        $log = $db->table('shop_stock_adjustments')
            ->where('product_id', $this->productId)
            ->get()->getRowArray();

        $this->assertNotNull($log);
        $this->assertSame(-3,         (int) $log['delta']);
        $this->assertSame('manual',   $log['source']);
        $this->assertSame('Sold offline', $log['note']);
    }

    public function test_adjust_variant_stock(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_product_variants')->insert([
            'product_id' => $this->productId, 'name' => 'Large', 'price_adjustment' => 0,
            'track_stock' => 1, 'stock_qty' => 5, 'position' => 0,
        ]);
        $variantId = (int) $db->insertID();

        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode'       => 'adjust',
            'delta'      => 10,
            'variant_id' => $variantId,
        ]);
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertSame($variantId, $body['variant_id']);
        $this->assertSame(15, $body['qty_after']);

        // Product-level stock must be unchanged
        $this->assertSame(20, $this->currentStock());
    }

    public function test_adjust_variant_not_on_this_product_returns_404(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_products')->insert(['slug' => 'other', 'name' => 'Other', 'price' => 5, 'active' => 1]);
        $otherId = (int) $db->insertID();
        $db->table('shop_product_variants')->insert([
            'product_id' => $otherId, 'name' => 'XL', 'price_adjustment' => 0, 'stock_qty' => 2, 'position' => 0,
        ]);
        $variantId = (int) $db->insertID();

        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode'       => 'adjust',
            'delta'      => 1,
            'variant_id' => $variantId,
        ]);
        $result->assertStatus(404);
    }

    // ── GET /admin/shop/products/:id/stock-history ──────────────────

    public function test_history_requires_auth(): void
    {
        $this->get("admin/shop/products/{$this->productId}/stock-history")->assertStatus(401);
    }

    public function test_history_returns_404_for_unknown_product(): void
    {
        $this->withAdmin()->get('admin/shop/products/9999/stock-history')->assertStatus(404);
    }

    public function test_history_returns_empty_on_no_adjustments(): void
    {
        $data = $this->json($this->withAdmin()->get("admin/shop/products/{$this->productId}/stock-history"));
        $this->assertSame([], $data['adjustments']);
    }

    public function test_history_returns_adjustments_in_reverse_chronological_order(): void
    {
        $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", ['mode' => 'adjust', 'delta' => 5,  'note' => 'First']);
        $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", ['mode' => 'adjust', 'delta' => -2, 'note' => 'Second']);

        $adjustments = $this->json($this->withAdmin()->get("admin/shop/products/{$this->productId}/stock-history"))['adjustments'];

        $this->assertCount(2, $adjustments);
        $this->assertSame('Second', $adjustments[0]['note']); // most recent first
        $this->assertSame('First',  $adjustments[1]['note']);
    }

    public function test_history_row_has_expected_fields(): void
    {
        $this->withAdmin()->post("admin/shop/products/{$this->productId}/stock-adjustment", [
            'mode' => 'set', 'qty' => 100, 'note' => 'Restock',
        ]);

        $adj = $this->json($this->withAdmin()->get("admin/shop/products/{$this->productId}/stock-history"))['adjustments'][0];

        $this->assertArrayHasKey('id',           $adj);
        $this->assertArrayHasKey('product_id',   $adj);
        $this->assertArrayHasKey('delta',         $adj);
        $this->assertArrayHasKey('qty_before',    $adj);
        $this->assertArrayHasKey('qty_after',     $adj);
        $this->assertArrayHasKey('source',        $adj);
        $this->assertArrayHasKey('note',          $adj);
        $this->assertArrayHasKey('created_at',    $adj);
        $this->assertSame('manual', $adj['source']);
        $this->assertSame('Restock', $adj['note']);
    }
}
