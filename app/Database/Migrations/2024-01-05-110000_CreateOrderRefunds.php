<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderRefunds extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE shop_order_refunds (
                id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id     INT UNSIGNED NOT NULL,
                amount_cents INT UNSIGNED NOT NULL,
                note         VARCHAR(500) NULL,
                created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_order (order_id),
                CONSTRAINT fk_refund_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE shop_order_refund_items (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                refund_id     INT UNSIGNED NOT NULL,
                order_item_id INT UNSIGNED NOT NULL,
                qty           INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                KEY idx_refund (refund_id),
                CONSTRAINT fk_ri_refund FOREIGN KEY (refund_id)     REFERENCES shop_order_refunds(id) ON DELETE CASCADE,
                CONSTRAINT fk_ri_item   FOREIGN KEY (order_item_id) REFERENCES shop_order_items(id)   ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS shop_order_refund_items');
        $this->db->query('DROP TABLE IF EXISTS shop_order_refunds');
    }
}
