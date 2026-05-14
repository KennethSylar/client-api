<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;
use App\Services\LowStockMailer;

/**
 * Tests for LowStockMailer::checkAndSend().
 *
 * Email delivery is not tested (requires RESEND_API_KEY + network).
 * We verify the observable side-effects instead:
 *   - low_stock_alerted_at gets stamped when the alert triggers
 *   - 24-hour debounce prevents re-stamping
 *   - No alert when stock is healthy or track_stock is off
 *
 * These tests also exercise the full adjustment → alert flow via the
 * POST /admin/shop/products/:id/stock-adjustment endpoint.
 */
class LowStockMailerTest extends FeatureTestCase
{
    private function seedProduct(array $overrides = []): array
    {
        $db       = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'slug'                => 'alert-test',
            'name'                => 'Alert Test Product',
            'price'               => 50.00,
            'track_stock'         => 1,
            'stock_qty'           => 20,
            'low_stock_threshold' => 5,
            'active'              => 1,
        ];
        $data = array_merge($defaults, $overrides);
        $db->table('shop_products')->insert($data);
        return array_merge($data, ['id' => (int) $db->insertID()]);
    }

    private function alertedAt(int $productId): ?string
    {
        $db  = \Config\Database::connect($this->DBGroup);
        $row = $db->table('shop_products')->where('id', $productId)->get()->getRowArray();
        return $row['low_stock_alerted_at'] ?? null;
    }

    // ── LowStockMailer::checkAndSend() unit-level ────────────────────

    public function test_no_alert_when_stock_is_healthy(): void
    {
        $prod = $this->seedProduct(['stock_qty' => 20, 'low_stock_threshold' => 5]);
        $db   = \Config\Database::connect($this->DBGroup);

        LowStockMailer::checkAndSend($db, $prod['id']);

        $this->assertNull($this->alertedAt($prod['id']));
    }

    public function test_no_alert_when_track_stock_is_disabled(): void
    {
        $prod = $this->seedProduct(['stock_qty' => 2, 'low_stock_threshold' => 5, 'track_stock' => 0]);
        $db   = \Config\Database::connect($this->DBGroup);

        LowStockMailer::checkAndSend($db, $prod['id']);

        $this->assertNull($this->alertedAt($prod['id']));
    }

    public function test_alert_stamps_low_stock_alerted_at_when_at_threshold(): void
    {
        $prod = $this->seedProduct(['stock_qty' => 5, 'low_stock_threshold' => 5]);
        $db   = \Config\Database::connect($this->DBGroup);

        LowStockMailer::checkAndSend($db, $prod['id']);

        $this->assertNotNull($this->alertedAt($prod['id']));
    }

    public function test_alert_stamps_when_stock_is_below_threshold(): void
    {
        $prod = $this->seedProduct(['stock_qty' => 2, 'low_stock_threshold' => 5]);
        $db   = \Config\Database::connect($this->DBGroup);

        LowStockMailer::checkAndSend($db, $prod['id']);

        $this->assertNotNull($this->alertedAt($prod['id']));
    }

    public function test_alert_stamps_when_stock_is_zero(): void
    {
        $prod = $this->seedProduct(['stock_qty' => 0, 'low_stock_threshold' => 5]);
        $db   = \Config\Database::connect($this->DBGroup);

        LowStockMailer::checkAndSend($db, $prod['id']);

        $this->assertNotNull($this->alertedAt($prod['id']));
    }

    public function test_debounce_skips_alert_within_24_hours(): void
    {
        $recentAlert = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago
        $prod = $this->seedProduct([
            'stock_qty'           => 2,
            'low_stock_threshold' => 5,
            'low_stock_alerted_at' => $recentAlert,
        ]);
        $db = \Config\Database::connect($this->DBGroup);

        LowStockMailer::checkAndSend($db, $prod['id']);

        // Timestamp must remain unchanged (not refreshed)
        $this->assertSame($recentAlert, $this->alertedAt($prod['id']));
    }

    public function test_debounce_allows_alert_after_24_hours(): void
    {
        $oldAlert = date('Y-m-d H:i:s', time() - (25 * 3600)); // 25 hours ago
        $prod = $this->seedProduct([
            'stock_qty'            => 2,
            'low_stock_threshold'  => 5,
            'low_stock_alerted_at' => $oldAlert,
        ]);
        $db = \Config\Database::connect($this->DBGroup);

        LowStockMailer::checkAndSend($db, $prod['id']);

        // Timestamp must be refreshed to now
        $this->assertNotSame($oldAlert, $this->alertedAt($prod['id']));
        $this->assertNotNull($this->alertedAt($prod['id']));
    }

    public function test_no_alert_for_unknown_product(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        // Must not throw
        LowStockMailer::checkAndSend($db, 99999);
        $this->assertTrue(true);
    }

    // ── Integration: adjustment endpoint triggers alert ──────────────

    public function test_stock_adjustment_triggers_alert_when_delta_is_negative_and_stock_drops_low(): void
    {
        $prod = $this->seedProduct(['stock_qty' => 6, 'low_stock_threshold' => 5]);

        $this->withAdmin()->post("admin/shop/products/{$prod['id']}/stock-adjustment", [
            'mode'  => 'adjust',
            'delta' => -2, // 6 → 4, which is <= threshold of 5
        ])->assertStatus(200);

        $this->assertNotNull($this->alertedAt($prod['id']));
    }

    public function test_stock_adjustment_does_not_trigger_alert_for_positive_delta(): void
    {
        $prod = $this->seedProduct(['stock_qty' => 3, 'low_stock_threshold' => 5]);

        $this->withAdmin()->post("admin/shop/products/{$prod['id']}/stock-adjustment", [
            'mode'  => 'adjust',
            'delta' => 10, // restocking — no alert
        ])->assertStatus(200);

        $this->assertNull($this->alertedAt($prod['id']));
    }

    public function test_stock_adjustment_does_not_retrigger_alert_within_24_hours(): void
    {
        $recentAlert = date('Y-m-d H:i:s', time() - 1800); // 30 min ago
        $prod = $this->seedProduct([
            'stock_qty'            => 3,
            'low_stock_threshold'  => 5,
            'low_stock_alerted_at' => $recentAlert,
        ]);

        $this->withAdmin()->post("admin/shop/products/{$prod['id']}/stock-adjustment", [
            'mode'  => 'adjust',
            'delta' => -1,
        ])->assertStatus(200);

        $this->assertSame($recentAlert, $this->alertedAt($prod['id']));
    }
}
