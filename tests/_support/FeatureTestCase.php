<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Base class for all HTTP feature tests.
 *
 * - Uses the `tests` DB group (client_cms_test) — never touches the dev DB.
 * - Runs all migrations fresh before the first test in each class.
 * - Wraps each test in a transaction → rolled back automatically, no teardown needed.
 * - Provides helpers: withAdmin(), enableShop().
 */
abstract class FeatureTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /** Use the dedicated test database group. */
    protected $DBGroup = 'tests';

    /** Search all namespaces for migrations (overrides CIUnitTestCase default of 'Tests\Support'). */
    protected $namespace = null;

    /** Run all migrations before the first test in this class. */
    protected $migrate = true;

    /** Run migrations once per test class, not before every test. */
    protected $migrateOnce = true;

    /** Do not drop/re-migrate between tests — tests clean up after themselves. */
    protected $refresh = false;

    // ----------------------------------------------------------------
    // Per-test reset
    // ----------------------------------------------------------------

    /**
     * Before each test: reset mutable shop/order data so tests are isolated.
     * Settings are reset to seeded defaults; transactional tables are truncated.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // All API endpoints accept JSON bodies.
        $this->withBodyFormat('json');

        $db = \Config\Database::connect($this->DBGroup);

        // Guard: only run cleanup if tables exist (i.e. migrations have run)
        if (! $db->tableExists('settings')) {
            return;
        }

        // Reset shop/gateway toggles to default (off) before each test
        $db->table('settings')->whereIn('key', [
            'shop_enabled', 'shop_payfast_enabled', 'shop_ozow_enabled',
        ])->update(['value' => '0']);

        // Remove test-inserted settings rows (keep seeded shop toggles)
        $db->table('settings')
            ->whereNotIn('key', ['shop_enabled', 'shop_payfast_enabled', 'shop_ozow_enabled'])
            ->delete();

        // Truncate all mutable tables so each test starts clean
        $db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'shop_order_status_log', 'shop_order_items', 'shop_orders',
            'shop_customer_sessions', 'shop_customers',
            'shop_stock_adjustments',
            'shop_product_variants', 'shop_product_images', 'shop_products',
            'shop_categories',
            'admin_sessions',
            'pages',
        ] as $table) {
            $db->query("TRUNCATE TABLE `{$table}`");
        }
        $db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insert a valid admin session token and return withCookies() wrapper
     * so the next request is authenticated.
     *
     * Usage:
     *   $result = $this->withAdmin()->post('admin/shop/categories', [...]);
     */
    protected function withAdmin(): static
    {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db = \Config\Database::connect($this->DBGroup);
        $db->table('admin_sessions')->insert([
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        service('superglobals')->setCookie('jnv_admin_session', $token);
        return $this;
    }

    /**
     * Set shop_enabled = 1 in the settings table.
     */
    protected function enableShop(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('settings')->where('key', 'shop_enabled')->update(['value' => '1']);
    }

    /**
     * Decode the JSON body from a FeatureResponse.
     */
    protected function json(\CodeIgniter\Test\TestResponse $response): array
    {
        return json_decode($response->response()->getBody(), true) ?? [];
    }
}
