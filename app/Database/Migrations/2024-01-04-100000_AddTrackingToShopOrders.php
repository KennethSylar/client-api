<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTrackingToShopOrders extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('shop_orders', [
            'tracking_carrier' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
                'after'      => 'notes',
            ],
            'tracking_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
                'after'      => 'tracking_carrier',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('shop_orders', ['tracking_carrier', 'tracking_number']);
    }
}
