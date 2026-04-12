<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * AdminPasswordSeeder
 *
 * Updates the admin password hash in the settings table.
 *
 * Usage:
 *   JNV_ADMIN_PASSWORD=yourpassword php spark db:seed AdminPasswordSeeder
 *
 * If JNV_ADMIN_PASSWORD is not set, prompts interactively.
 */
class AdminPasswordSeeder extends Seeder
{
    public function run(): void
    {
        $password = getenv('JNV_ADMIN_PASSWORD') ?: null;

        if (!$password) {
            echo 'Enter new admin password: ';
            // Hide input on UNIX terminals
            if (PHP_OS_FAMILY !== 'Windows') {
                system('stty -echo');
                $password = trim((string) fgets(STDIN));
                system('stty echo');
                echo "\n";
            } else {
                $password = trim((string) fgets(STDIN));
            }
        }

        if (empty($password)) {
            echo "Error: password cannot be empty.\n";
            exit(1);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $existing = $this->db->table('settings')->where('key', 'admin_password_hash')->get()->getRowArray();

        if ($existing) {
            $this->db->table('settings')
                ->where('key', 'admin_password_hash')
                ->update(['value' => $hash]);
        } else {
            $this->db->table('settings')->insert(['key' => 'admin_password_hash', 'value' => $hash]);
        }

        echo "✓ Admin password updated.\n";
    }
}
