<?php

namespace App\Controllers\Content;

use App\Controllers\BaseController;

/**
 * Content\Settings
 *
 * Public endpoint: returns all site settings as a key-value object.
 * Mirrors: GET /api/content/settings
 */
class Settings extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('settings')->get()->getResultArray();

        $settings = [];
        foreach ($rows as $row) {
            $key   = $row['key'];
            $value = $row['value'];

            // Decode JSON-encoded values (e.g. accreditations array)
            if ($key === 'accreditations') {
                $decoded = json_decode($value, true);
                $value   = is_array($decoded) ? $decoded : [];
            }

            // Never expose the password hash to the public
            if ($key === 'admin_password_hash') {
                continue;
            }

            $settings[$key] = $value;
        }

        return $this->json($settings);
    }
}
