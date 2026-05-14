<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * shop_stock_adjustments — audit log for all stock quantity changes.
 *
 * Populated by:
 *   - Manual admin adjustments (POST /admin/shop/products/{id}/stock-adjustment)
 *   - Order placement (source = 'order', reference_id = order_id)       [M5]
 *   - Refunds / restocks (source = 'refund', reference_id = order_id)   [M6]
 */
class CreateShopStockAdjustments extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `shop_stock_adjustments` (
                `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `product_id`  INT UNSIGNED    NOT NULL,
                `variant_id`  INT UNSIGNED    DEFAULT NULL,
                `delta`       INT             NOT NULL,          -- positive = stock added, negative = stock removed
                `qty_before`  INT             NOT NULL,
                `qty_after`   INT             NOT NULL,
                `source`      ENUM('manual','order','refund','import') NOT NULL DEFAULT 'manual',
                `reference_id` INT UNSIGNED   DEFAULT NULL,      -- order_id when source = order|refund
                `note`        VARCHAR(500)    NOT NULL DEFAULT '',
                `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_product`  (`product_id`),
                KEY `idx_variant`  (`variant_id`),
                KEY `idx_source`   (`source`),
                CONSTRAINT `fk_adj_product` FOREIGN KEY (`product_id`)
                    REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_adj_variant` FOREIGN KEY (`variant_id`)
                    REFERENCES `shop_product_variants` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `shop_stock_adjustments`');
    }
}
