<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderTables extends Migration
{
    public function up(): void
    {
        // ── Customers ────────────────────────────────────────────────
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'first_name'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'last_name'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'default' => null],
            'password_hash'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'email_verified' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('shop_customers', true);

        // ── Orders ───────────────────────────────────────────────────
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'token'              => ['type' => 'VARCHAR', 'constraint' => 64],
            'customer_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            // Billing / shipping snapshot
            'first_name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'last_name'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'              => ['type' => 'VARCHAR', 'constraint' => 255],
            'phone'              => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'default' => null],
            'address_line1'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'address_line2'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'city'               => ['type' => 'VARCHAR', 'constraint' => 100],
            'province'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'postal_code'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'country'            => ['type' => 'VARCHAR', 'constraint' => 2, 'default' => 'ZA'],
            // Financials (stored in cents to avoid float errors)
            'subtotal_cents'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'vat_cents'          => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'shipping_cents'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_cents'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'currency'           => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'ZAR'],
            // Status & payment
            'status'             => ['type' => 'ENUM', 'constraint' => ['pending','paid','processing','shipped','delivered','cancelled','refunded'], 'default' => 'pending'],
            'payment_gateway'    => ['type' => 'ENUM', 'constraint' => ['payfast','ozow','none'], 'null' => true, 'default' => null],
            'payment_reference'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null],
            'paid_at'            => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'notes'              => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'created_at'         => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('email');
        $this->forge->addKey('status');
        $this->forge->createTable('shop_orders', true);

        // ── Order items ──────────────────────────────────────────────
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'order_id'        => ['type' => 'INT', 'unsigned' => true],
            'product_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'variant_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            // Snapshot the names/price at order time
            'product_name'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'variant_name'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'qty'             => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'unit_price_cents'=> ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'line_total_cents'=> ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'sku'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->createTable('shop_order_items', true);

        // ── Order status log ─────────────────────────────────────────
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'order_id'   => ['type' => 'INT', 'unsigned' => true],
            'from_status'=> ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'default' => null],
            'to_status'  => ['type' => 'VARCHAR', 'constraint' => 30],
            'note'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'default' => null],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->createTable('shop_order_status_log', true);

        // ── Settings additions ───────────────────────────────────────
        $db = $this->db;
        $rows = [
            ['key' => 'shop_payfast_enabled',   'value' => '0'],
            ['key' => 'shop_payfast_merchant_id','value' => ''],
            ['key' => 'shop_payfast_merchant_key','value' => ''],
            ['key' => 'shop_payfast_passphrase', 'value' => ''],
            ['key' => 'shop_ozow_enabled',       'value' => '0'],
            ['key' => 'shop_ozow_site_code',     'value' => ''],
            ['key' => 'shop_ozow_private_key',   'value' => ''],
            ['key' => 'shop_ozow_api_key',        'value' => ''],
            ['key' => 'shop_notification_email', 'value' => ''],
        ];
        foreach ($rows as $row) {
            $exists = $db->table('settings')->where('key', $row['key'])->countAllResults();
            if (!$exists) {
                $db->table('settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('shop_order_status_log', true);
        $this->forge->dropTable('shop_order_items', true);
        $this->forge->dropTable('shop_orders', true);
        $this->forge->dropTable('shop_customers', true);
    }
}
