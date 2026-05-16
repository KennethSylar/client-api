<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUserToAdminSessions extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE `admin_sessions`
                ADD COLUMN `user_id` INT UNSIGNED NULL    AFTER `token`,
                ADD COLUMN `role`    ENUM('admin','shop_admin') NULL AFTER `user_id`,
                ADD KEY `idx_user_id` (`user_id`)
        ");
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `admin_sessions` DROP KEY `idx_user_id`');
        $this->db->query('ALTER TABLE `admin_sessions` DROP COLUMN `role`');
        $this->db->query('ALTER TABLE `admin_sessions` DROP COLUMN `user_id`');
    }
}
