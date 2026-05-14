<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   POST /shop/payment/payfast/notify
 *   POST /shop/payment/ozow/notify
 */
class PaymentNotifyTest extends FeatureTestCase
{
    // ── Seeding helpers ───────────────────────────────────────────────

    private function seedOrder(array $overrides = []): array
    {
        $db = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'token'             => bin2hex(random_bytes(32)),
            'first_name'        => 'Jane',
            'last_name'         => 'Doe',
            'email'             => 'jane@example.com',
            'phone'             => '0821234567',
            'address_line1'     => '1 Main St',
            'address_line2'     => null,
            'city'              => 'Cape Town',
            'province'          => 'Western Cape',
            'postal_code'       => '8001',
            'country'           => 'ZA',
            'subtotal_cents'    => 10000,
            'vat_cents'         => 0,
            'shipping_cents'    => 0,
            'total_cents'       => 10000,   // R100.00
            'currency'          => 'ZAR',
            'status'            => 'pending',
            'payment_gateway'   => 'payfast',
            'payment_reference' => null,
            'paid_at'           => null,
            'notes'             => null,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];
        $row = array_merge($defaults, $overrides);
        $db->table('shop_orders')->insert($row);
        $row['id'] = (int) $db->insertID();
        return $row;
    }

    private function seedProduct(array $overrides = []): array
    {
        $db = \Config\Database::connect($this->DBGroup);
        $slug = $overrides['slug'] ?? 'product-' . bin2hex(random_bytes(4));
        $defaults = [
            'slug'        => $slug,
            'name'        => 'Test Product',
            'description' => '',
            'price'       => '100.00',
            'active'      => 1,
            'track_stock' => 1,
            'stock_qty'   => 10,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        $row = array_merge($defaults, $overrides);
        $db->table('shop_products')->insert($row);
        $row['id'] = (int) $db->insertID();
        return $row;
    }

    private function seedOrderItem(int $orderId, int $productId, array $overrides = []): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_order_items')->insert(array_merge([
            'order_id'         => $orderId,
            'product_id'       => $productId,
            'variant_id'       => null,
            'product_name'     => 'Test Product',
            'variant_name'     => null,
            'qty'              => 1,
            'unit_price_cents' => 10000,
            'line_total_cents' => 10000,
        ], $overrides));
    }

    private function seedSetting(string $key, string $value): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $existing = $db->table('settings')->where('key', $key)->get()->getRowArray();
        if ($existing) {
            $db->table('settings')->where('key', $key)->update(['value' => $value]);
        } else {
            $db->table('settings')->insert(['key' => $key, 'value' => $value]);
        }
    }

    /**
     * Build a valid PayFast ITN payload and compute its signature.
     */
    private function payfastPayload(int $orderId, float $amount, string $paymentStatus, string $passphrase = ''): array
    {
        $data = [
            'm_payment_id'  => (string) $orderId,
            'pf_payment_id' => 'PF-' . $orderId,
            'payment_status'=> $paymentStatus,
            'item_name'     => 'Order #' . $orderId,
            'amount_gross'  => number_format($amount, 2, '.', ''),
        ];
        ksort($data);
        $sigString = http_build_query($data);
        if ($passphrase !== '') {
            $sigString .= '&passphrase=' . urlencode($passphrase);
        }
        $data['signature'] = md5($sigString);
        return $data;
    }

    /**
     * Build a valid Ozow notify payload and compute its hash.
     */
    private function ozowPayload(int $orderId, string $status, string $privateKey = ''): array
    {
        $siteCode    = 'TESTSITE';
        $countryCode = 'ZA';
        $currency    = 'ZAR';
        $amount      = '100.00';
        $transRef    = (string) $orderId;
        $transId     = 'OZ-' . $orderId;
        $isTest      = 'true';

        $hashInput = strtolower($siteCode . $countryCode . $currency . $amount . $transRef . $transId . $status . $isTest . $privateKey);
        $hash = hash('sha512', $hashInput);

        return [
            'SiteCode'             => $siteCode,
            'CountryCode'          => $countryCode,
            'CurrencyCode'         => $currency,
            'Amount'               => $amount,
            'Status'               => $status,
            'TransactionReference' => $transRef,
            'TransactionId'        => $transId,
            'IsTest'               => $isTest,
            'HashCheck'            => $hash,
        ];
    }

    // ── POST /shop/payment/payfast/notify ────────────────────────────

    public function test_payfast_returns_400_for_missing_payment_id(): void
    {
        $result = $this->post('shop/payment/payfast/notify', [
            'payment_status' => 'COMPLETE',
        ]);
        $this->assertSame(400, $result->response()->getStatusCode());
    }

    public function test_payfast_returns_400_for_missing_payment_status(): void
    {
        $result = $this->post('shop/payment/payfast/notify', [
            'm_payment_id' => '1',
        ]);
        $this->assertSame(400, $result->response()->getStatusCode());
    }

    public function test_payfast_returns_404_for_unknown_order(): void
    {
        $result = $this->post('shop/payment/payfast/notify', [
            'm_payment_id'   => '99999',
            'payment_status' => 'COMPLETE',
            'signature'      => md5(''),
        ]);
        $this->assertSame(404, $result->response()->getStatusCode());
    }

    public function test_payfast_returns_400_for_invalid_signature(): void
    {
        $order  = $this->seedOrder(['total_cents' => 10000]);

        $result = $this->post('shop/payment/payfast/notify', [
            'm_payment_id'   => (string) $order['id'],
            'payment_status' => 'COMPLETE',
            'amount_gross'   => '100.00',
            'signature'      => 'badsignature',
        ]);
        $this->assertSame(400, $result->response()->getStatusCode());
    }

    public function test_payfast_returns_400_for_amount_mismatch(): void
    {
        $order    = $this->seedOrder(['total_cents' => 10000]);
        $payload  = $this->payfastPayload($order['id'], 50.00, 'COMPLETE');

        $result = $this->post('shop/payment/payfast/notify', $payload);
        $this->assertSame(400, $result->response()->getStatusCode());
    }

    public function test_payfast_complete_marks_order_as_paid(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->payfastPayload($order['id'], 100.00, 'COMPLETE');

        $result = $this->post('shop/payment/payfast/notify', $payload);
        $this->assertSame(200, $result->response()->getStatusCode());

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('paid', $updated['status']);
        $this->assertSame('PF-' . $order['id'], $updated['payment_reference']);
        $this->assertNotNull($updated['paid_at']);
    }

    public function test_payfast_complete_logs_status_change(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->payfastPayload($order['id'], 100.00, 'COMPLETE');

        $this->post('shop/payment/payfast/notify', $payload);

        $db  = \Config\Database::connect($this->DBGroup);
        $log = $db->table('shop_order_status_log')->where('order_id', $order['id'])->get()->getRowArray();
        $this->assertSame('pending', $log['from_status']);
        $this->assertSame('paid',    $log['to_status']);
    }

    public function test_payfast_complete_is_idempotent_for_already_paid_order(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000, 'status' => 'paid']);
        $payload = $this->payfastPayload($order['id'], 100.00, 'COMPLETE');

        $this->post('shop/payment/payfast/notify', $payload);

        $db      = \Config\Database::connect($this->DBGroup);
        $count   = $db->table('shop_order_status_log')->where('order_id', $order['id'])->countAllResults();
        $this->assertSame(0, $count); // no new log entry for already-paid order
    }

    public function test_payfast_failed_marks_order_as_cancelled(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->payfastPayload($order['id'], 100.00, 'FAILED');

        $result = $this->post('shop/payment/payfast/notify', $payload);
        $this->assertSame(200, $result->response()->getStatusCode());

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('cancelled', $updated['status']);
    }

    public function test_payfast_cancelled_marks_order_as_cancelled(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->payfastPayload($order['id'], 100.00, 'CANCELLED');

        $this->post('shop/payment/payfast/notify', $payload);

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('cancelled', $updated['status']);
    }

    public function test_payfast_cancelled_restores_product_stock(): void
    {
        $product = $this->seedProduct(['stock_qty' => 5, 'track_stock' => 1]);
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $this->seedOrderItem($order['id'], $product['id'], ['qty' => 2]);

        $payload = $this->payfastPayload($order['id'], 100.00, 'CANCELLED');
        $this->post('shop/payment/payfast/notify', $payload);

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_products')->where('id', $product['id'])->get()->getRowArray();
        $this->assertSame(7, (int) $updated['stock_qty']); // 5 + 2 restored
    }

    public function test_payfast_pending_does_not_change_order_status(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->payfastPayload($order['id'], 100.00, 'PENDING');

        $result = $this->post('shop/payment/payfast/notify', $payload);
        $this->assertSame(200, $result->response()->getStatusCode());

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('pending', $updated['status']);
    }

    public function test_payfast_complete_with_passphrase_verifies_correctly(): void
    {
        $this->seedSetting('shop_payfast_passphrase', 'test-pass');

        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->payfastPayload($order['id'], 100.00, 'COMPLETE', 'test-pass');

        $result = $this->post('shop/payment/payfast/notify', $payload);
        $this->assertSame(200, $result->response()->getStatusCode());

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('paid', $updated['status']);
    }

    // ── POST /shop/payment/ozow/notify ───────────────────────────────

    public function test_ozow_returns_400_for_missing_transaction_reference(): void
    {
        $result = $this->post('shop/payment/ozow/notify', ['Status' => 'Complete']);
        $this->assertSame(400, $result->response()->getStatusCode());
    }

    public function test_ozow_returns_400_for_missing_status(): void
    {
        $result = $this->post('shop/payment/ozow/notify', ['TransactionReference' => '1']);
        $this->assertSame(400, $result->response()->getStatusCode());
    }

    public function test_ozow_returns_404_for_unknown_order(): void
    {
        $payload = $this->ozowPayload(99999, 'Complete');

        $result = $this->post('shop/payment/ozow/notify', $payload);
        $this->assertSame(404, $result->response()->getStatusCode());
    }

    public function test_ozow_returns_400_for_invalid_hash(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->ozowPayload($order['id'], 'Complete');
        $payload['HashCheck'] = 'badhash';

        $result = $this->post('shop/payment/ozow/notify', $payload);
        $this->assertSame(400, $result->response()->getStatusCode());
    }

    public function test_ozow_complete_marks_order_as_paid(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->ozowPayload($order['id'], 'Complete');

        $result = $this->post('shop/payment/ozow/notify', $payload);
        $this->assertSame(200, $result->response()->getStatusCode());

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('paid', $updated['status']);
    }

    public function test_ozow_complete_logs_status_change(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->ozowPayload($order['id'], 'Complete');

        $this->post('shop/payment/ozow/notify', $payload);

        $db  = \Config\Database::connect($this->DBGroup);
        $log = $db->table('shop_order_status_log')->where('order_id', $order['id'])->get()->getRowArray();
        $this->assertSame('pending', $log['from_status']);
        $this->assertSame('paid',    $log['to_status']);
    }

    public function test_ozow_cancelled_marks_order_as_cancelled(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->ozowPayload($order['id'], 'Cancelled');

        $this->post('shop/payment/ozow/notify', $payload);

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('cancelled', $updated['status']);
    }

    public function test_ozow_error_marks_order_as_cancelled(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->ozowPayload($order['id'], 'Error');

        $this->post('shop/payment/ozow/notify', $payload);

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('cancelled', $updated['status']);
    }

    public function test_ozow_error_restores_product_stock(): void
    {
        $product = $this->seedProduct(['stock_qty' => 3, 'track_stock' => 1]);
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $this->seedOrderItem($order['id'], $product['id'], ['qty' => 1]);

        $payload = $this->ozowPayload($order['id'], 'Error');
        $this->post('shop/payment/ozow/notify', $payload);

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_products')->where('id', $product['id'])->get()->getRowArray();
        $this->assertSame(4, (int) $updated['stock_qty']); // 3 + 1
    }

    public function test_ozow_pending_does_not_change_order_status(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->ozowPayload($order['id'], 'PendingInvestigation');

        $result = $this->post('shop/payment/ozow/notify', $payload);
        $this->assertSame(200, $result->response()->getStatusCode());

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('pending', $updated['status']);
    }

    public function test_ozow_complete_with_private_key_verifies_correctly(): void
    {
        $this->seedSetting('shop_ozow_private_key', 'my-secret-key');

        $order   = $this->seedOrder(['total_cents' => 10000]);
        $payload = $this->ozowPayload($order['id'], 'Complete', 'my-secret-key');

        $result = $this->post('shop/payment/ozow/notify', $payload);
        $this->assertSame(200, $result->response()->getStatusCode());

        $db      = \Config\Database::connect($this->DBGroup);
        $updated = $db->table('shop_orders')->where('id', $order['id'])->get()->getRowArray();
        $this->assertSame('paid', $updated['status']);
    }

    public function test_ozow_complete_is_idempotent_for_already_paid_order(): void
    {
        $order   = $this->seedOrder(['total_cents' => 10000, 'status' => 'paid']);
        $payload = $this->ozowPayload($order['id'], 'Complete');

        $this->post('shop/payment/ozow/notify', $payload);

        $db    = \Config\Database::connect($this->DBGroup);
        $count = $db->table('shop_order_status_log')->where('order_id', $order['id'])->countAllResults();
        $this->assertSame(0, $count);
    }
}
