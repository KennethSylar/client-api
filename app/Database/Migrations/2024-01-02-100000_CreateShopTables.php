<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates all tables required by the e-commerce shop feature.
 *
 *   shop_categories     — product categories (hierarchical via parent_id)
 *   shop_products       — products with stock, VAT-exempt flag, and single-product landing content
 *   shop_product_images — Cloudinary image URLs per product, ordered by position
 *   shop_product_variants — size/colour/etc variants with price adjustment and own stock
 */
class CreateShopTables extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `shop_categories` (
                `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
                `parent_id`  INT UNSIGNED   DEFAULT NULL,
                `slug`       VARCHAR(120)   NOT NULL,
                `name`       VARCHAR(255)   NOT NULL,
                `position`   SMALLINT       NOT NULL DEFAULT 0,
                `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_slug` (`slug`),
                KEY `idx_parent` (`parent_id`),
                CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`)
                    REFERENCES `shop_categories` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `shop_products` (
                `id`                     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `category_id`            INT UNSIGNED    DEFAULT NULL,
                `slug`                   VARCHAR(120)    NOT NULL,
                `name`                   VARCHAR(255)    NOT NULL,
                `description`            LONGTEXT        NOT NULL,
                `price`                  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
                `vat_exempt`             TINYINT(1)      NOT NULL DEFAULT 0,
                -- Stock management
                `track_stock`            TINYINT(1)      NOT NULL DEFAULT 1,
                `stock_qty`              INT             NOT NULL DEFAULT 0,
                `low_stock_threshold`    INT             NOT NULL DEFAULT 5,
                -- Single-product landing page content (JSON)
                -- Keys: hero_tagline, hero_media_url, hero_media_type (image|video),
                --       features ([{icon,title,body}]), specs ([{label,value}]),
                --       gallery ([url])
                `landing_content`        JSON            DEFAULT NULL,
                `active`                 TINYINT(1)      NOT NULL DEFAULT 1,
                `created_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_slug` (`slug`),
                KEY `idx_category` (`category_id`),
                KEY `idx_active` (`active`),
                CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`)
                    REFERENCES `shop_categories` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `shop_product_images` (
                `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
                `product_id` INT UNSIGNED   NOT NULL,
                `url`        VARCHAR(500)   NOT NULL,
                `alt`        VARCHAR(255)   NOT NULL DEFAULT '',
                `position`   SMALLINT       NOT NULL DEFAULT 0,
                `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_product` (`product_id`),
                CONSTRAINT `fk_image_product` FOREIGN KEY (`product_id`)
                    REFERENCES `shop_products` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `shop_product_variants` (
                `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `product_id`       INT UNSIGNED    NOT NULL,
                `name`             VARCHAR(255)    NOT NULL,
                `price_adjustment` DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
                `track_stock`      TINYINT(1)      NOT NULL DEFAULT 1,
                `stock_qty`        INT             NOT NULL DEFAULT 0,
                `position`         SMALLINT        NOT NULL DEFAULT 0,
                `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_product` (`product_id`),
                CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`)
                    REFERENCES `shop_products` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed shop settings into the shared settings table
        $db = $this->db;
        $settings = [
            ['key' => 'shop_enabled',            'value' => '0'],
            ['key' => 'shop_mode',               'value' => 'multi'],   // multi | single
            ['key' => 'shop_featured_product_slug','value' => ''],
            ['key' => 'shop_currency',           'value' => 'ZAR'],
            ['key' => 'shop_vat_enabled',        'value' => '0'],
            ['key' => 'shop_vat_rate',           'value' => '15'],
            ['key' => 'shop_shipping_rate',      'value' => '0.00'],
            ['key' => 'shop_free_shipping_from', 'value' => ''],        // empty = no free-shipping threshold
            ['key' => 'shop_low_stock_alert_email', 'value' => ''],    // defaults to contactToEmail if empty
        ];
        foreach ($settings as $row) {
            $db->table('settings')
               ->ignore(true)
               ->insert($row);
        }
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `shop_product_variants`');
        $this->db->query('DROP TABLE IF EXISTS `shop_product_images`');
        $this->db->query('DROP TABLE IF EXISTS `shop_products`');
        $this->db->query('DROP TABLE IF EXISTS `shop_categories`');

        $db = $this->db;
        $db->table('settings')->whereIn('key', [
            'shop_enabled',
            'shop_mode',
            'shop_featured_product_slug',
            'shop_currency',
            'shop_vat_enabled',
            'shop_vat_rate',
            'shop_shipping_rate',
            'shop_free_shipping_from',
            'shop_low_stock_alert_email',
        ])->delete();
    }
}
