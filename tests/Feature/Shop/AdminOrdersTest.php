<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   GET   admin/shop/orders
 *   GET   admin/shop/orders/:id
 *   PATCH admin/shop/orders/:id/status
 *   POST  admin/shop/orders/:id/refund
 *   GET   admin/shop/orders/:id/invoice
 */
class AdminOrdersTest extends FeatureTestCase
{
    // ── Seeding helpers ───────────────────────────────────────────────

    /**
     * Insert a minimal shop order and return its DB id.
     */
    private function seedOrder(array $overrides = []): int
    {
        $db = \Config\Database::connect($this->DBGroup);

        $db->table('shop_orders')->insert(array_merge([
            'token'          => bin2hex(random_bytes(32)),
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane@example.com',
            'phone'          => '0821234567',
            'address_line1'  => '1 Main Street',
            'address_line2'  => null,
            'city'           => 'Cape Town',
            'province'       => 'Western Cape',
            'postal_code'    => '8001',
            'country'        => 'ZA',
            'subtotal_cents' => 10000,
            'vat_cents'      => 1500,
            'shipping_cents' => 0,
            'total_cents'    => 11500,
            'currency'       => 'ZAR',
            'status'         => 'pending',
            'payment_gateway'=> null,
            'payment_reference' => null,
            'paid_at'        => null,
            'notes'          => null,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ], $overrides));

        return (int) $db->insertID();
    }

    /**
     * Insert an order item linked to an order.
     */
    private function seedOrderItem(int $orderId, array $overrides = []): int
    {
        $db = \Config\Database::connect($this->DBGroup);

        $db->table('shop_order_items')->insert(array_merge([
            'order_id'         => $orderId,
            'product_id'       => null,
            'variant_id'       => null,
            'product_name'     => 'Widget',
            'variant_name'     => null,
            'qty'              => 1,
            'unit_price_cents' => 10000,
            'line_total_cents' => 10000,
            'sku'              => null,
        ], $overrides));

        return (int) $db->insertID();
    }

    // ── Auth guards ───────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get('admin/shop/orders')->assertStatus(401);
    }

    public function test_show_requires_auth(): void
    {
        $this->get('admin/shop/orders/1')->assertStatus(401);
    }

    public function test_update_status_requires_auth(): void
    {
        $this->patch('admin/shop/orders/1/status', ['status' => 'paid'])->assertStatus(401);
    }

    public function test_refund_requires_auth(): void
    {
        $this->post('admin/shop/orders/1/refund', [])->assertStatus(401);
    }

    public function test_invoice_requires_auth(): void
    {
        $this->get('admin/shop/orders/1/invoice')->assertStatus(401);
    }

    // ── GET admin/shop/orders ─────────────────────────────────────────

    public function test_index_returns_200_with_empty_list(): void
    {
        $result = $this->withAdmin()->get('admin/shop/orders');
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertCount(0, $body['data']);
        $this->assertSame(0, $body['meta']['total']);
    }

    public function test_index_returns_paginated_orders(): void
    {
        $this->seedOrder(['email' => 'a@example.com']);
        $this->seedOrder(['email' => 'b@example.com']);

        $result = $this->withAdmin()->get('admin/shop/orders');
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertSame(2, $body['meta']['total']);
        $this->assertCount(2, $body['data']);
    }

    public function test_index_meta_contains_expected_keys(): void
    {
        $body = $this->json($this->withAdmin()->get('admin/shop/orders'));
        $meta = $body['meta'];

        $this->assertArrayHasKey('total', $meta);
        $this->assertArrayHasKey('page', $meta);
        $this->assertArrayHasKey('per_page', $meta);
        $this->assertArrayHasKey('pages', $meta);
    }

    public function test_index_filters_by_status(): void
    {
        $this->seedOrder(['status' => 'paid']);
        $this->seedOrder(['status' => 'pending']);
        $this->seedOrder(['status' => 'paid']);

        $result = $this->withAdmin()->get('admin/shop/orders?status=paid');
        $body   = $this->json($result);

        $this->assertSame(2, $body['meta']['total']);
        foreach ($body['data'] as $order) {
            $this->assertSame('paid', $order['status']);
        }
    }

    public function test_index_searches_by_email(): void
    {
        $this->seedOrder(['email' => 'findme@example.com']);
        $this->seedOrder(['email' => 'other@example.com']);

        $result = $this->withAdmin()->get('admin/shop/orders?search=findme');
        $body   = $this->json($result);

        $this->assertSame(1, $body['meta']['total']);
        $this->assertSame('findme@example.com', $body['data'][0]['email']);
    }

    public function test_index_searches_by_first_name(): void
    {
        $this->seedOrder(['first_name' => 'Alice', 'email' => 'a@example.com']);
        $this->seedOrder(['first_name' => 'Bob',   'email' => 'b@example.com']);

        $result = $this->withAdmin()->get('admin/shop/orders?search=Alice');
        $body   = $this->json($result);

        $this->assertSame(1, $body['meta']['total']);
        $this->assertSame('Alice', $body['data'][0]['first_name']);
    }

    public function test_index_respects_per_page_param(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedOrder(['email' => "user{$i}@example.com"]);
        }

        $result = $this->withAdmin()->get('admin/shop/orders?per_page=2&page=1');
        $body   = $this->json($result);

        $this->assertCount(2, $body['data']);
        $this->assertSame(5, $body['meta']['total']);
        $this->assertSame(2, $body['meta']['per_page']);
    }

    public function test_index_order_contains_expected_fields(): void
    {
        $this->seedOrder();

        $body  = $this->json($this->withAdmin()->get('admin/shop/orders'));
        $order = $body['data'][0];

        foreach (['id', 'token', 'status', 'first_name', 'last_name', 'email', 'total_cents', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $order, "Missing key: {$key}");
        }
    }

    // ── GET admin/shop/orders/:id ─────────────────────────────────────

    public function test_show_returns_404_for_unknown_order(): void
    {
        $this->withAdmin()->get('admin/shop/orders/99999')->assertStatus(404);
    }

    public function test_show_returns_order_with_items_and_status_log(): void
    {
        $orderId = $this->seedOrder(['status' => 'paid']);
        $this->seedOrderItem($orderId, ['product_name' => 'Sprocket', 'qty' => 2]);

        $result = $this->withAdmin()->get("admin/shop/orders/{$orderId}");
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertSame($orderId, $body['id']);
        $this->assertArrayHasKey('items', $body);
        $this->assertArrayHasKey('status_log', $body);
        $this->assertCount(1, $body['items']);
        $this->assertSame('Sprocket', $body['items'][0]['product_name']);
        $this->assertSame(2, $body['items'][0]['qty']);
    }

    public function test_show_returns_correct_cents_as_integers(): void
    {
        $orderId = $this->seedOrder([
            'subtotal_cents' => 10000,
            'vat_cents'      => 1500,
            'total_cents'    => 11500,
        ]);

        $body = $this->json($this->withAdmin()->get("admin/shop/orders/{$orderId}"));

        $this->assertSame(10000, $body['subtotal_cents']);
        $this->assertSame(1500, $body['vat_cents']);
        $this->assertSame(11500, $body['total_cents']);
    }

    public function test_show_returns_empty_status_log_for_new_order(): void
    {
        $orderId = $this->seedOrder();

        $body = $this->json($this->withAdmin()->get("admin/shop/orders/{$orderId}"));

        $this->assertIsArray($body['status_log']);
        $this->assertCount(0, $body['status_log']);
    }

    // ── PATCH admin/shop/orders/:id/status ───────────────────────────

    public function test_update_status_returns_404_for_unknown_order(): void
    {
        $this->withAdmin()->patch('admin/shop/orders/99999/status', ['status' => 'shipped'])->assertStatus(404);
    }

    public function test_update_status_returns_400_for_invalid_status(): void
    {
        $orderId = $this->seedOrder();

        $result = $this->withAdmin()->patch("admin/shop/orders/{$orderId}/status", ['status' => 'exploded']);
        $result->assertStatus(400);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_update_status_returns_400_for_empty_status(): void
    {
        $orderId = $this->seedOrder();

        $this->withAdmin()->patch("admin/shop/orders/{$orderId}/status", ['status' => ''])->assertStatus(400);
    }

    public function test_update_status_updates_order_and_returns_new_status(): void
    {
        $orderId = $this->seedOrder(['status' => 'paid']);

        $result = $this->withAdmin()->patch("admin/shop/orders/{$orderId}/status", [
            'status' => 'processing',
            'note'   => 'Picking started',
        ]);
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertSame('processing', $body['status']);
    }

    public function test_update_status_persists_to_db(): void
    {
        $orderId = $this->seedOrder(['status' => 'paid']);

        $this->withAdmin()->patch("admin/shop/orders/{$orderId}/status", ['status' => 'shipped']);

        $db    = \Config\Database::connect($this->DBGroup);
        $order = $db->table('shop_orders')->where('id', $orderId)->get()->getRowArray();
        $this->assertSame('shipped', $order['status']);
    }

    public function test_update_status_writes_status_log_entry(): void
    {
        $orderId = $this->seedOrder(['status' => 'processing']);

        $this->withAdmin()->patch("admin/shop/orders/{$orderId}/status", [
            'status' => 'shipped',
            'note'   => 'Tracking: XYZ123',
        ]);

        $db  = \Config\Database::connect($this->DBGroup);
        $log = $db->table('shop_order_status_log')
            ->where('order_id', $orderId)
            ->get()->getResultArray();

        $this->assertCount(1, $log);
        $this->assertSame('processing', $log[0]['from_status']);
        $this->assertSame('shipped', $log[0]['to_status']);
        $this->assertSame('Tracking: XYZ123', $log[0]['note']);
    }

    public function test_update_status_allows_all_valid_statuses(): void
    {
        $allowed = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

        foreach ($allowed as $status) {
            $orderId = $this->seedOrder();
            $result  = $this->withAdmin()->patch("admin/shop/orders/{$orderId}/status", ['status' => $status]);
            $result->assertStatus(200, "Status '{$status}' should be accepted");
        }
    }

    // ── POST admin/shop/orders/:id/refund ─────────────────────────────

    public function test_refund_returns_404_for_unknown_order(): void
    {
        $this->withAdmin()->post('admin/shop/orders/99999/refund', [])->assertStatus(404);
    }

    public function test_refund_returns_400_for_pending_order(): void
    {
        $orderId = $this->seedOrder(['status' => 'pending']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", [])->assertStatus(400);
    }

    public function test_refund_returns_400_for_cancelled_order(): void
    {
        $orderId = $this->seedOrder(['status' => 'cancelled']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", [])->assertStatus(400);
    }

    public function test_refund_returns_400_for_already_refunded_order(): void
    {
        $orderId = $this->seedOrder(['status' => 'refunded']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", [])->assertStatus(400);
    }

    public function test_refund_returns_200_for_paid_order(): void
    {
        $orderId = $this->seedOrder(['status' => 'paid']);

        $result = $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", ['note' => 'Customer request']);
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertSame('refunded', $body['status']);
    }

    public function test_refund_returns_200_for_processing_order(): void
    {
        $orderId = $this->seedOrder(['status' => 'processing']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", [])->assertStatus(200);
    }

    public function test_refund_returns_200_for_shipped_order(): void
    {
        $orderId = $this->seedOrder(['status' => 'shipped']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", [])->assertStatus(200);
    }

    public function test_refund_returns_200_for_delivered_order(): void
    {
        $orderId = $this->seedOrder(['status' => 'delivered']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", [])->assertStatus(200);
    }

    public function test_refund_updates_order_status_to_refunded_in_db(): void
    {
        $orderId = $this->seedOrder(['status' => 'paid']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", []);

        $db    = \Config\Database::connect($this->DBGroup);
        $order = $db->table('shop_orders')->where('id', $orderId)->get()->getRowArray();
        $this->assertSame('refunded', $order['status']);
    }

    public function test_refund_writes_status_log_entry(): void
    {
        $orderId = $this->seedOrder(['status' => 'paid']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", ['note' => 'Damaged goods']);

        $db  = \Config\Database::connect($this->DBGroup);
        $log = $db->table('shop_order_status_log')
            ->where('order_id', $orderId)
            ->get()->getResultArray();

        $this->assertCount(1, $log);
        $this->assertSame('paid', $log[0]['from_status']);
        $this->assertSame('refunded', $log[0]['to_status']);
        $this->assertSame('Damaged goods', $log[0]['note']);
    }

    public function test_refund_uses_default_note_when_none_provided(): void
    {
        $orderId = $this->seedOrder(['status' => 'paid']);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", []);

        $db  = \Config\Database::connect($this->DBGroup);
        $log = $db->table('shop_order_status_log')
            ->where('order_id', $orderId)
            ->get()->getRowArray();

        $this->assertSame('Manual refund by admin', $log['note']);
    }

    public function test_refund_restores_product_stock_when_track_stock_enabled(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        // Create a product with stock tracking enabled
        $db->table('shop_products')->insert([
            'slug'        => 'tracked-product',
            'name'        => 'Tracked Product',
            'description' => '',
            'price'       => 100.00,
            'track_stock' => 1,
            'stock_qty'   => 5,
            'active'      => 1,
        ]);
        $productId = (int) $db->insertID();

        $orderId = $this->seedOrder(['status' => 'paid']);
        $this->seedOrderItem($orderId, [
            'product_id'  => $productId,
            'qty'         => 2,
        ]);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", []);

        $product = $db->table('shop_products')->where('id', $productId)->get()->getRowArray();
        $this->assertSame(7, (int) $product['stock_qty']); // 5 + 2 = 7
    }

    public function test_refund_does_not_restore_stock_when_track_stock_disabled(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        $db->table('shop_products')->insert([
            'slug'        => 'untracked-product',
            'name'        => 'Untracked Product',
            'description' => '',
            'price'       => 50.00,
            'track_stock' => 0,
            'stock_qty'   => 10,
            'active'      => 1,
        ]);
        $productId = (int) $db->insertID();

        $orderId = $this->seedOrder(['status' => 'paid']);
        $this->seedOrderItem($orderId, [
            'product_id' => $productId,
            'qty'        => 3,
        ]);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", []);

        $product = $db->table('shop_products')->where('id', $productId)->get()->getRowArray();
        // Stock should remain 10 — not restored
        $this->assertSame(10, (int) $product['stock_qty']);
    }

    public function test_refund_restores_variant_stock_when_track_stock_enabled(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        $db->table('shop_products')->insert([
            'slug' => 'variant-product', 'name' => 'Variant Product',
            'description' => '', 'price' => 100.00, 'track_stock' => 0, 'stock_qty' => 0, 'active' => 1,
        ]);
        $productId = (int) $db->insertID();

        $db->table('shop_product_variants')->insert([
            'product_id' => $productId, 'name' => 'Large',
            'price_adjustment' => 0, 'track_stock' => 1, 'stock_qty' => 3, 'position' => 0,
        ]);
        $variantId = (int) $db->insertID();

        $orderId = $this->seedOrder(['status' => 'paid']);
        $this->seedOrderItem($orderId, [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'qty'        => 2,
        ]);

        $this->withAdmin()->post("admin/shop/orders/{$orderId}/refund", []);

        $variant = $db->table('shop_product_variants')->where('id', $variantId)->get()->getRowArray();
        $this->assertSame(5, (int) $variant['stock_qty']); // 3 + 2 = 5
    }

    // ── GET admin/shop/orders/:id/invoice ─────────────────────────────

    public function test_invoice_returns_404_for_unknown_order(): void
    {
        $this->withAdmin()->get('admin/shop/orders/99999/invoice')->assertStatus(404);
    }

    public function test_invoice_returns_pdf_content_type(): void
    {
        $orderId = $this->seedOrder();

        $result = $this->withAdmin()->get("admin/shop/orders/{$orderId}/invoice");
        $result->assertStatus(200);

        $contentType = $result->response()->getHeaderLine('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);
    }

    public function test_invoice_response_has_content_disposition_header(): void
    {
        $orderId = $this->seedOrder();

        $result = $this->withAdmin()->get("admin/shop/orders/{$orderId}/invoice");

        $disposition = $result->response()->getHeaderLine('Content-Disposition');
        $this->assertStringContainsString('invoice-' . $orderId, $disposition);
    }

    public function test_invoice_response_body_is_not_empty(): void
    {
        $orderId = $this->seedOrder();

        $result = $this->withAdmin()->get("admin/shop/orders/{$orderId}/invoice");
        $body   = $result->response()->getBody();

        $this->assertNotEmpty($body);
    }
}
