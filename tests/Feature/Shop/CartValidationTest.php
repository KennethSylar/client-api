<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for POST /shop/cart/validate
 */
class CartValidationTest extends FeatureTestCase
{
    private function seedProduct(array $overrides = []): array
    {
        $db = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'slug'        => 'test-product-' . uniqid(),
            'name'        => 'Test Product',
            'price'       => '100.00',
            'track_stock' => 1,
            'stock_qty'   => 10,
            'active'      => 1,
        ];
        $data = array_merge($defaults, $overrides);
        $db->table('shop_products')->insert($data);
        return array_merge($data, ['id' => (int) $db->insertID()]);
    }

    private function seedVariant(int $productId, array $overrides = []): array
    {
        $db = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'product_id'       => $productId,
            'name'             => 'Large',
            'price_adjustment' => '10.00',
            'track_stock'      => 1,
            'stock_qty'        => 5,
            'position'         => 0,
        ];
        $data = array_merge($defaults, $overrides);
        $db->table('shop_product_variants')->insert($data);
        return array_merge($data, ['id' => (int) $db->insertID()]);
    }

    // ── Guards ───────────────────────────────────────────────────────

    public function test_returns_503_when_shop_disabled(): void
    {
        $this->post('shop/cart/validate', ['items' => []])->assertStatus(503);
    }

    public function test_requires_items_array(): void
    {
        $this->enableShop();
        $this->post('shop/cart/validate', [])->assertStatus(400);
        $this->post('shop/cart/validate', ['items' => []])->assertStatus(400);
    }

    // ── Happy path ───────────────────────────────────────────────────

    public function test_validates_single_item_with_no_issues(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct(['price' => '50.00', 'stock_qty' => 5]);

        $result = $this->post('shop/cart/validate', [
            'items' => [['product_id' => $prod['id'], 'qty' => 2, 'price' => 50.00]],
        ]);
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertFalse($data['has_issues']);
        $this->assertCount(1, $data['items']);

        $item = $data['items'][0];
        $this->assertSame($prod['id'], $item['product_id']);
        $this->assertSame(2, $item['qty_adjusted']);
        $this->assertTrue($item['in_stock']);
        $this->assertFalse($item['stock_changed']);
        $this->assertFalse($item['price_changed']);
    }

    public function test_detects_price_change(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct(['price' => '75.00', 'stock_qty' => 10]);

        $data = $this->json($this->post('shop/cart/validate', [
            'items' => [['product_id' => $prod['id'], 'qty' => 1, 'price' => 50.00]], // client has old price
        ]));

        $this->assertTrue($data['has_issues']);
        $this->assertTrue($data['items'][0]['price_changed']);
        $this->assertEqualsWithDelta(75.0, $data['items'][0]['effective_price'], 0.001);
    }

    public function test_reduces_qty_when_stock_is_insufficient(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct(['stock_qty' => 3]);

        $data = $this->json($this->post('shop/cart/validate', [
            'items' => [['product_id' => $prod['id'], 'qty' => 5, 'price' => 100.00]],
        ]));

        $this->assertTrue($data['has_issues']);
        $item = $data['items'][0];
        $this->assertTrue($item['stock_changed']);
        $this->assertSame(5, $item['qty_requested']);
        $this->assertSame(3, $item['qty_adjusted']);
        $this->assertSame(3, $item['qty_available']);
    }

    public function test_marks_item_out_of_stock_when_stock_is_zero(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct(['stock_qty' => 0]);

        $data = $this->json($this->post('shop/cart/validate', [
            'items' => [['product_id' => $prod['id'], 'qty' => 1, 'price' => 100.00]],
        ]));

        $this->assertTrue($data['has_issues']);
        $item = $data['items'][0];
        $this->assertFalse($item['in_stock']);
        $this->assertSame(0, $item['qty_adjusted']);
    }

    public function test_marks_removed_item_when_product_not_found(): void
    {
        $this->enableShop();

        $data = $this->json($this->post('shop/cart/validate', [
            'items' => [['product_id' => 99999, 'qty' => 1, 'price' => 10.00, 'name' => 'Ghost']],
        ]));

        $this->assertTrue($data['has_issues']);
        $item = $data['items'][0];
        $this->assertTrue($item['removed']);
        $this->assertFalse($item['in_stock']);
    }

    public function test_marks_removed_item_when_product_is_inactive(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct(['active' => 0]);

        $data = $this->json($this->post('shop/cart/validate', [
            'items' => [['product_id' => $prod['id'], 'qty' => 1, 'price' => 100.00]],
        ]));

        $this->assertTrue($data['has_issues']);
        $this->assertTrue($data['items'][0]['removed']);
    }

    public function test_validates_variant_price_and_stock(): void
    {
        $this->enableShop();
        $prod    = $this->seedProduct(['price' => '100.00', 'stock_qty' => 10]);
        $variant = $this->seedVariant($prod['id'], ['price_adjustment' => '20.00', 'stock_qty' => 2]);

        $data = $this->json($this->post('shop/cart/validate', [
            'items' => [[
                'product_id' => $prod['id'],
                'variant_id' => $variant['id'],
                'qty'        => 5,
                'price'      => 120.00, // correct effective price
            ]],
        ]));

        $this->assertTrue($data['has_issues']); // qty reduced
        $item = $data['items'][0];
        $this->assertSame(2, $item['qty_adjusted']);      // capped at variant stock
        $this->assertFalse($item['price_changed']);       // price was correct
        $this->assertEqualsWithDelta(120.0, $item['effective_price'], 0.001);
    }

    public function test_untracked_stock_always_approves_qty(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct(['track_stock' => 0, 'stock_qty' => 0]);

        $data = $this->json($this->post('shop/cart/validate', [
            'items' => [['product_id' => $prod['id'], 'qty' => 100, 'price' => 100.00]],
        ]));

        $this->assertFalse($data['has_issues']);
        $this->assertSame(100, $data['items'][0]['qty_adjusted']);
    }

    public function test_validates_multiple_items_independently(): void
    {
        $this->enableShop();
        $good = $this->seedProduct(['slug' => 'good', 'price' => '50.00', 'stock_qty' => 10]);
        $low  = $this->seedProduct(['slug' => 'low',  'price' => '30.00', 'stock_qty' => 1]);

        $data = $this->json($this->post('shop/cart/validate', [
            'items' => [
                ['product_id' => $good['id'], 'qty' => 2, 'price' => 50.00],
                ['product_id' => $low['id'],  'qty' => 3, 'price' => 30.00],
            ],
        ]));

        $this->assertTrue($data['has_issues']);
        $this->assertCount(2, $data['items']);

        $byId = array_column($data['items'], null, 'product_id');
        $this->assertFalse($byId[$good['id']]['stock_changed']);
        $this->assertTrue($byId[$low['id']]['stock_changed']);
        $this->assertSame(1, $byId[$low['id']]['qty_adjusted']);
    }
}
