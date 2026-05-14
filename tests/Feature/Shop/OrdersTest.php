<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for admin order management endpoints.
 */
class OrdersTest extends FeatureTestCase
{
    // ── Seed helpers ─────────────────────────────────────────────────

    private function seedProduct(array $overrides = []): array
    {
        $db       = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'slug'        => 'prod-' . uniqid(),
            'name'        => 'Test Product',
            'price'       => '50.00',
            'track_stock' => 1,
            'stock_qty'   => 20,
            'active'      => 1,
        ];
        $data = array_merge($defaults, $overrides);
        $db->table('shop_products')->insert($data);
        return array_merge($data, ['id' => (int)$db->insertID()]);
    }

    private function seedOrder(array $overrides = []): array
    {
        $db       = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'token'          => bin2hex(random_bytes(16)),
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane@example.com',
            'address_line1'  => '12 Main Rd',
            'city'           => 'Cape Town',
            'postal_code'    => '8001',
            'country'        => 'ZA',
            'subtotal_cents' => 10000,
            'vat_cents'      => 0,
            'shipping_cents' => 0,
            'total_cents'    => 10000,
            'currency'       => 'ZAR',
            'status'         => 'paid',
            'payment_gateway'=> 'payfast',
            'paid_at'        => date('Y-m-d H:i:s'),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        $data = array_merge($defaults, $overrides);
        $db->table('shop_orders')->insert($data);
        return array_merge($data, ['id' => (int)$db->insertID()]);
    }

    private function seedOrderItem(int $orderId, int $productId, array $overrides = []): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_order_items')->insert(array_merge([
            'order_id'         => $orderId,
            'product_id'       => $productId,
            'product_name'     => 'Test Product',
            'qty'              => 2,
            'unit_price_cents' => 5000,
            'line_total_cents' => 10000,
        ], $overrides));
    }

    // ── Guards ───────────────────────────────────────────────────────

    public function test_index_requires_admin(): void
    {
        $this->get('admin/shop/orders')->assertStatus(401);
    }

    public function test_show_requires_admin(): void
    {
        $this->get('admin/shop/orders/1')->assertStatus(401);
    }

    // ── List ─────────────────────────────────────────────────────────

    public function test_lists_orders(): void
    {
        $this->enableShop();
        $this->seedOrder(['email' => 'a@test.com']);
        $this->seedOrder(['email' => 'b@test.com']);

        $data = $this->json($this->withAdmin()->get('admin/shop/orders'));

        $this->assertCount(2, $data['data']);
        $this->assertSame(2, $data['meta']['total']);
    }

    public function test_filters_orders_by_status(): void
    {
        $this->enableShop();
        $this->seedOrder(['status' => 'pending']);
        $this->seedOrder(['status' => 'paid']);

        $data = $this->json($this->withAdmin()->get('admin/shop/orders?status=pending'));
        $this->assertCount(1, $data['data']);
        $this->assertSame('pending', $data['data'][0]['status']);
    }

    public function test_searches_orders_by_email(): void
    {
        $this->enableShop();
        $this->seedOrder(['email' => 'find-me@example.com']);
        $this->seedOrder(['email' => 'other@example.com']);

        $data = $this->json($this->withAdmin()->get('admin/shop/orders?search=find-me'));
        $this->assertCount(1, $data['data']);
        $this->assertSame('find-me@example.com', $data['data'][0]['email']);
    }

    // ── Show ─────────────────────────────────────────────────────────

    public function test_shows_order_with_items_and_log(): void
    {
        $this->enableShop();
        $prod  = $this->seedProduct();
        $order = $this->seedOrder();
        $this->seedOrderItem($order['id'], $prod['id']);

        // Seed a status log entry
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_order_status_log')->insert([
            'order_id'    => $order['id'],
            'from_status' => 'pending',
            'to_status'   => 'paid',
            'note'        => 'PayFast confirmed',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $data = $this->json($this->withAdmin()->get("admin/shop/orders/{$order['id']}"));

        $this->assertSame($order['id'], $data['id']);
        $this->assertCount(1, $data['items']);
        $this->assertCount(1, $data['status_log']);
        $this->assertSame('paid', $data['status_log'][0]['to']);
    }

    public function test_show_returns_404_for_unknown_order(): void
    {
        $this->withAdmin()->get('admin/shop/orders/99999')->assertStatus(404);
    }

    // ── Status update ────────────────────────────────────────────────

    public function test_updates_order_status(): void
    {
        $this->enableShop();
        $order = $this->seedOrder(['status' => 'paid']);

        $result = $this->withAdmin()->patch("admin/shop/orders/{$order['id']}/status", [
            'status' => 'shipped',
            'note'   => 'Sent via courier',
        ]);
        $result->assertStatus(200);

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('shipped', $updated['status']);

        $log = $db->table('shop_order_status_log')
            ->where('order_id', $order['id'])
            ->where('to_status', 'shipped')
            ->get()->getRowArray();
        $this->assertNotEmpty($log);
        $this->assertSame('Sent via courier', $log['note']);
    }

    public function test_rejects_invalid_status(): void
    {
        $this->enableShop();
        $order = $this->seedOrder();

        $result = $this->withAdmin()->patch("admin/shop/orders/{$order['id']}/status", [
            'status' => 'flying',
        ]);
        $result->assertStatus(400);
    }

    // ── Refund ───────────────────────────────────────────────────────

    public function test_refund_marks_order_and_restores_stock(): void
    {
        $this->enableShop();
        $prod  = $this->seedProduct(['stock_qty' => 5]);
        $order = $this->seedOrder(['status' => 'paid']);
        $this->seedOrderItem($order['id'], $prod['id'], ['qty' => 2]);

        $result = $this->withAdmin()->post("admin/shop/orders/{$order['id']}/refund", [
            'note' => 'Customer requested',
        ]);
        $result->assertStatus(200);

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('refunded', $updated['status']);

        $updatedProd = $db->table('shop_products')->where('id', $prod['id'])->get()->getRowArray();
        $this->assertSame(7, (int)$updatedProd['stock_qty']); // 5 + 2 restored
    }

    public function test_refund_rejected_for_pending_order(): void
    {
        $this->enableShop();
        $order = $this->seedOrder(['status' => 'pending']);

        $result = $this->withAdmin()->post("admin/shop/orders/{$order['id']}/refund", []);
        $result->assertStatus(400);
    }

    public function test_refund_is_idempotent_for_already_refunded(): void
    {
        $this->enableShop();
        $order = $this->seedOrder(['status' => 'refunded']);

        $result = $this->withAdmin()->post("admin/shop/orders/{$order['id']}/refund", []);
        $result->assertStatus(400); // cannot refund a refunded order
    }
}
