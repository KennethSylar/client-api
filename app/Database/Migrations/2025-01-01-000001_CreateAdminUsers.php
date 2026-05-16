<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdminUsers extends Migration
{
    public function up(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `admin_users` (
                `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `name`          VARCHAR(100)  NOT NULL,
                `email`         VARCHAR(255)  NOT NULL,
                `password_hash` VARCHAR(255)  NOT NULL,
                `role`          ENUM('admin','shop_admin') NOT NULL DEFAULT 'admin',
                `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
                `created_at`    DATETIME      NOT NULL,
                `updated_at`    DATETIME      NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `admin_users`');
    }
}
