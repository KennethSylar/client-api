<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPartialRefundStatus extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE shop_orders
            MODIFY status ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded','partially_refunded')
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        $this->db->query("
            ALTER TABLE shop_orders
            MODIFY status ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded')
            NOT NULL DEFAULT 'pending'
        ");
    }
}
