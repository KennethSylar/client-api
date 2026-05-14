<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds low_stock_alerted_at to shop_products for 24-hour debounce.
 * NULL means no alert has ever been sent for this product.
 */
class AddLowStockAlertedAt extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE `shop_products`
            ADD COLUMN `low_stock_alerted_at` DATETIME DEFAULT NULL
                AFTER `low_stock_threshold`
        ");
    }

    public function down(): void
    {
        $this->db->query("
            ALTER TABLE `shop_products`
            DROP COLUMN `low_stock_alerted_at`
        ");
    }
}
