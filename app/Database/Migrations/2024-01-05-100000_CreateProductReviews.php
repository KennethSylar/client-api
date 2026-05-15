<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductReviews extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE shop_product_reviews (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id  INT UNSIGNED NOT NULL,
                customer_id INT UNSIGNED NOT NULL,
                order_id    INT UNSIGNED NOT NULL,
                rating      TINYINT      NOT NULL,
                title       VARCHAR(255) NOT NULL DEFAULT '',
                body        TEXT         NULL,
                status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                admin_note  VARCHAR(500) NULL,
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_customer_product (customer_id, product_id),
                KEY idx_product_status (product_id, status),
                KEY idx_status (status),
                CONSTRAINT fk_review_product  FOREIGN KEY (product_id)  REFERENCES shop_products(id)   ON DELETE CASCADE,
                CONSTRAINT fk_review_customer FOREIGN KEY (customer_id) REFERENCES shop_customers(id)  ON DELETE CASCADE,
                CONSTRAINT fk_review_order    FOREIGN KEY (order_id)    REFERENCES shop_orders(id)     ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS shop_product_reviews');
    }
}
