<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates all tables required by the JNV CMS.
 *
 *   settings       — key/value site configuration (incl. admin password hash)
 *   admin_sessions — stateless session tokens for the admin panel
 *   pages          — CMS pages (slug + JSON data blob)
 *   newsletters    — downloadable newsletter PDFs
 *   documents      — downloadable document PDFs, grouped by category
 */
class CreateCoreTables extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `settings` (
                `key`   VARCHAR(100)  NOT NULL,
                `value` TEXT          NOT NULL,
                PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `admin_sessions` (
                `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `token`      CHAR(64)        NOT NULL,
                `expires_at` DATETIME        NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_token` (`token`),
                KEY `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `pages` (
                `slug`       VARCHAR(120)  NOT NULL,
                `data`       LONGTEXT      NOT NULL,
                `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `newsletters` (
                `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `issue`          VARCHAR(100)    NOT NULL,
                `title`          VARCHAR(255)    NOT NULL,
                `description`    TEXT            NOT NULL,
                `filename`       VARCHAR(255)    NOT NULL DEFAULT '',
                `file_url`       VARCHAR(500)    NOT NULL DEFAULT '',
                `file_size`      VARCHAR(30)     NOT NULL DEFAULT '',
                `published_date` DATE            DEFAULT NULL,
                `published`      TINYINT(1)      NOT NULL DEFAULT 1,
                `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `documents` (
                `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                `category`    VARCHAR(150)    NOT NULL,
                `title`       VARCHAR(255)    NOT NULL,
                `description` TEXT            NOT NULL,
                `filename`    VARCHAR(255)    NOT NULL DEFAULT '',
                `file_url`    VARCHAR(500)    NOT NULL DEFAULT '',
                `file_size`   VARCHAR(30)     NOT NULL DEFAULT '',
                `published`   TINYINT(1)      NOT NULL DEFAULT 1,
                `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_category` (`category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `documents`');
        $this->db->query('DROP TABLE IF EXISTS `newsletters`');
        $this->db->query('DROP TABLE IF EXISTS `pages`');
        $this->db->query('DROP TABLE IF EXISTS `admin_sessions`');
        $this->db->query('DROP TABLE IF EXISTS `settings`');
    }
}
