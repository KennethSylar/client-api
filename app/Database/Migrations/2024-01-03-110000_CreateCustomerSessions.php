<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomerSessions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'INT', 'unsigned' => true],
            'token'       => ['type' => 'VARCHAR', 'constraint' => 64],
            'expires_at'  => ['type' => 'DATETIME'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('customer_id');
        $this->forge->createTable('shop_customer_sessions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('shop_customer_sessions', true);
    }
}
