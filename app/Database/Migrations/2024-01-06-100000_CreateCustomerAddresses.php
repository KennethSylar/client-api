<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomerAddresses extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id'  => ['type' => 'INT', 'unsigned' => true],
            'label'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'first_name'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'last_name'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'phone'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'address_line1'=> ['type' => 'VARCHAR', 'constraint' => 255],
            'address_line2'=> ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'city'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'province'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'postal_code'  => ['type' => 'VARCHAR', 'constraint' => 20],
            'country'      => ['type' => 'VARCHAR', 'constraint' => 2, 'default' => 'ZA'],
            'is_default'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME'],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('customer_id', 'shop_customers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey('customer_id');

        $this->forge->createTable('shop_customer_addresses');
    }

    public function down(): void
    {
        $this->forge->dropTable('shop_customer_addresses', true);
    }
}
