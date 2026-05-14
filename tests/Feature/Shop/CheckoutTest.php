<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for POST /shop/checkout and GET /shop/orders/:token
 */
class CheckoutTest extends FeatureTestCase
{
    // ── Seed helpers ─────────────────────────────────────────────────

    private function seedProduct(array $overrides = []): array
    {
        $db       = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'slug'        => 'test-product-' . uniqid(),
            'name'        => 'Test Product',
            'price'       => '50.00',
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
        $db       = \Config\Database::connect($this->DBGroup);
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

    private function enableGateway(string $gateway): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $key = "shop_{$gateway}_enabled";
        $exists = $db->table('settings')->where('key', $key)->countAllResults();
        if ($exists) {
            $db->table('settings')->where('key', $key)->update(['value' => '1']);
        } else {
            $db->table('settings')->insert(['key' => $key, 'value' => '1']);
        }
    }

    private function validPayload(array $productItems, string $gateway = 'payfast'): array
    {
        return [
            'first_name'    => 'Jane',
            'last_name'     => 'Doe',
            'email'         => 'jane@example.com',
            'phone'         => '0821234567',
            'address_line1' => '12 Main Rd',
            'city'          => 'Cape Town',
            'postal_code'   => '8001',
            'country'       => 'ZA',
            'gateway'       => $gateway,
            'items'         => $productItems,
        ];
    }

    // ── Guards ───────────────────────────────────────────────────────

    public function test_returns_503_when_shop_disabled(): void
    {
        $this->post('shop/checkout', [])->assertStatus(503);
    }

    public function test_requires_required_fields(): void
    {
        $this->enableShop();
        $this->post('shop/checkout', [])->assertStatus(400);
    }

    public function test_requires_valid_email(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct();
        $this->enableGateway('payfast');

        $payload = $this->validPayload([
            ['product_id' => $prod['id'], 'qty' => 1, 'price' => 50.00],
        ]);
        $payload['email'] = 'not-an-email';

        $this->post('shop/checkout', $payload)->assertStatus(400);
    }

    public function test_rejects_disabled_gateway(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct();

        $result = $this->post('shop/checkout', $this->validPayload(
            [['product_id' => $prod['id'], 'qty' => 1, 'price' => 50.00]],
            'payfast'
        ));
        $result->assertStatus(400);
    }

    // ── Happy path ───────────────────────────────────────────────────

    public function test_creates_order_and_returns_payment_url(): void
    {
        $this->enableShop();
        $this->enableGateway('payfast');
        $prod = $this->seedProduct(['price' => '100.00', 'stock_qty' => 5]);

        $result = $this->post('shop/checkout', $this->validPayload([
            ['product_id' => $prod['id'], 'qty' => 2, 'price' => 100.00],
        ]));
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertNotEmpty($data['order_token']);
        $this->assertStringContainsString('payfast', $data['payment_url']);
        $this->assertSame('payfast', $data['gateway']);

        // Order record created
        $db    = \Config\Database::connect($this->DBGroup);
        $order = $db->table('shop_orders')->where('token', $data['order_token'])->get()->getRowArray();
        $this->assertNotEmpty($order);
        $this->assertSame('pending', $order['status']);
        $this->assertSame(20000, (int)$order['total_cents']); // 2 × R100
    }

    public function test_decrements_stock_on_order(): void
    {
        $this->enableShop();
        $this->enableGateway('payfast');
        $prod = $this->seedProduct(['stock_qty' => 10]);

        $this->post('shop/checkout', $this->validPayload([
            ['product_id' => $prod['id'], 'qty' => 3, 'price' => 50.00],
        ]))->assertStatus(200);

        $db = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_products')->where('id', $prod['id'])->get()->getRowArray();
        $this->assertSame(7, (int)$updated['stock_qty']); // 10 - 3
    }

    public function test_creates_order_with_ozow_gateway(): void
    {
        $this->enableShop();
        $this->enableGateway('ozow');
        $prod = $this->seedProduct();

        $result = $this->post('shop/checkout', $this->validPayload(
            [['product_id' => $prod['id'], 'qty' => 1, 'price' => 50.00]],
            'ozow'
        ));
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertStringContainsString('ozow', $data['payment_url']);
        $this->assertSame('ozow', $data['gateway']);
    }

    // ── Stock enforcement ────────────────────────────────────────────

    public function test_rejects_order_when_out_of_stock(): void
    {
        $this->enableShop();
        $this->enableGateway('payfast');
        $prod = $this->seedProduct(['stock_qty' => 0]);

        $result = $this->post('shop/checkout', $this->validPayload([
            ['product_id' => $prod['id'], 'qty' => 1, 'price' => 50.00],
        ]));
        $result->assertStatus(409);
    }

    public function test_rejects_order_when_qty_exceeds_stock(): void
    {
        $this->enableShop();
        $this->enableGateway('payfast');
        $prod = $this->seedProduct(['stock_qty' => 2]);

        $result = $this->post('shop/checkout', $this->validPayload([
            ['product_id' => $prod['id'], 'qty' => 5, 'price' => 50.00],
        ]));
        $result->assertStatus(409);
    }

    public function test_rejects_order_for_inactive_product(): void
    {
        $this->enableShop();
        $this->enableGateway('payfast');
        $prod = $this->seedProduct(['active' => 0]);

        $result = $this->post('shop/checkout', $this->validPayload([
            ['product_id' => $prod['id'], 'qty' => 1, 'price' => 50.00],
        ]));
        $result->assertStatus(409);
    }

    // ── Variants ─────────────────────────────────────────────────────

    public function test_handles_variant_stock_correctly(): void
    {
        $this->enableShop();
        $this->enableGateway('payfast');
        $prod    = $this->seedProduct(['price' => '100.00', 'stock_qty' => 20]);
        $variant = $this->seedVariant($prod['id'], ['stock_qty' => 3, 'price_adjustment' => '20.00']);

        $result = $this->post('shop/checkout', $this->validPayload([
            ['product_id' => $prod['id'], 'variant_id' => $variant['id'], 'qty' => 2, 'price' => 120.00],
        ]));
        $result->assertStatus(200);

        $db = \Config\Database::connect($this->DBGroup);
        $updatedVariant = $db->table('shop_product_variants')->where('id', $variant['id'])->get()->getRowArray();
        $this->assertSame(1, (int)$updatedVariant['stock_qty']); // 3 - 2

        // Product stock unchanged (variant tracks its own)
        $updatedProd = $db->table('shop_products')->where('id', $prod['id'])->get()->getRowArray();
        $this->assertSame(20, (int)$updatedProd['stock_qty']);
    }

    // ── Order lookup ─────────────────────────────────────────────────

    public function test_order_lookup_by_token(): void
    {
        $this->enableShop();
        $this->enableGateway('payfast');
        $prod = $this->seedProduct(['price' => '75.00', 'stock_qty' => 5]);

        $checkoutData = $this->json($this->post('shop/checkout', $this->validPayload([
            ['product_id' => $prod['id'], 'qty' => 1, 'price' => 75.00],
        ])));

        $token  = $checkoutData['order_token'];
        $result = $this->get("shop/orders/{$token}");
        $result->assertStatus(200);

        $order = $this->json($result);
        $this->assertSame($token, $order['token']);
        $this->assertSame('pending', $order['status']);
        $this->assertCount(1, $order['items']);
        $this->assertSame('Test Product', $order['items'][0]['product_name']);
    }

    public function test_order_lookup_returns_404_for_unknown_token(): void
    {
        $this->enableShop();
        $this->get('shop/orders/nonexistenttokenabc123')->assertStatus(404);
    }
}
