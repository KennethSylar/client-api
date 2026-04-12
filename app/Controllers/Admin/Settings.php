<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Admin\Settings
 *
 * Protected: updates site settings.
 * Mirrors: PUT /api/admin/settings
 */
class Settings extends BaseController
{
    public function update(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        if (empty($body)) {
            return $this->error('No data provided.', 400);
        }

        $db = \Config\Database::connect();

        foreach ($body as $key => $value) {
            // Special handling: plain-text password → bcrypt hash
            if ($key === 'admin_password_hash' && !empty($value)) {
                $value = password_hash((string) $value, PASSWORD_BCRYPT);
            }

            // Special handling: accreditations array → JSON string
            if ($key === 'accreditations' && is_array($value)) {
                $value = json_encode($value);
            }

            // INSERT ... ON DUPLICATE KEY UPDATE (upsert)
            $existing = $db->table('settings')->where('key', $key)->get()->getRowArray();

            if ($existing) {
                $db->table('settings')->where('key', $key)->update(['value' => (string) $value]);
            } else {
                $db->table('settings')->insert(['key' => $key, 'value' => (string) $value]);
            }
        }

        return $this->ok();
    }
}
