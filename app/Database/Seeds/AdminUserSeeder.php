<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Creates the initial admin user from the legacy admin_password_hash setting.
 * Run once after deploying the RBAC migration.
 *
 * Usage:
 *   php spark db:seed AdminUserSeeder
 *
 * If no admin_password_hash exists in settings, a random password is generated
 * and printed to stdout — change it immediately via Admin > Users.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if an admin user already exists
        $exists = $this->db->table('admin_users')
            ->where('role', 'admin')
            ->countAllResults();

        if ($exists > 0) {
            echo "AdminUserSeeder: admin user already exists — skipping.\n";
            return;
        }

        // Read legacy password hash from settings
        $row = $this->db->table('settings')
            ->where('key', 'admin_password_hash')
            ->get()->getRowArray();

        if (!empty($row['value'])) {
            $hash = $row['value'];
            // bcryptjs $2b$ prefix → PHP $2y$
            if (str_starts_with($hash, '$2b$')) {
                $hash = '$2y$' . substr($hash, 4);
            }
            echo "AdminUserSeeder: migrating existing password hash.\n";
        } else {
            // No existing password — generate a secure random one
            $plaintext = bin2hex(random_bytes(12));
            $hash      = password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => 12]);
            echo "AdminUserSeeder: no existing password found.\n";
            echo "  Generated password: {$plaintext}\n";
            echo "  Change this immediately via Admin > Users.\n";
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('admin_users')->insert([
            'name'          => 'Admin',
            'email'         => 'admin@example.com',
            'password_hash' => $hash,
            'role'          => 'admin',
            'is_active'     => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        echo "AdminUserSeeder: admin user created (email: admin@example.com).\n";
        echo "  Update the email and name via Admin > Users after first login.\n";
    }
}
