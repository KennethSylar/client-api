<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\App;

/**
 * Admin\Auth
 *
 * Handles admin authentication.
 * Mirrors:
 *   POST /api/admin/login   (no auth filter)
 *   POST /api/admin/logout  (adminauth filter)
 *   GET  /api/admin/me      (adminauth filter)
 */
class Auth extends BaseController
{
    public function login(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body     = $this->jsonBody();
        $password = $body['password'] ?? '';

        if (empty($password)) {
            return $this->error('Password is required.', 400);
        }

        $db   = \Config\Database::connect();
        $row  = $db->table('settings')
                   ->where('key', 'admin_password_hash')
                   ->get()
                   ->getRowArray();

        $hash = $row['value'] ?? '';

        // bcryptjs produces $2b$ prefix; normalise to $2y$ which PHP always accepts.
        $normalised = str_starts_with($hash, '$2b$')
            ? '$2y$' . substr($hash, 4)
            : $hash;

        if (empty($hash) || !password_verify($password, $normalised)) {
            return $this->error('Invalid password.', 401);
        }

        // Clean up expired sessions
        $db->table('admin_sessions')
           ->where('expires_at <', date('Y-m-d H:i:s'))
           ->delete();

        // Create a new session token (64 hex chars = 32 random bytes)
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $db->table('admin_sessions')->insert([
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        // Set HTTP-only cookie (24 h)
        // `secure` is derived from baseURL protocol — true in prod (https), false in dev (http)
        $isHttps = str_starts_with(config(App::class)->baseURL, 'https');
        set_cookie([
            'name'     => 'jnv_admin_session',
            'value'    => $token,
            'expire'   => 86400,
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return $this->ok();
    }

    public function logout(): \CodeIgniter\HTTP\ResponseInterface
    {
        $token = get_cookie('jnv_admin_session');

        if (!empty($token)) {
            $db = \Config\Database::connect();
            $db->table('admin_sessions')->where('token', $token)->delete();
        }

        delete_cookie('jnv_admin_session');

        return $this->ok();
    }

    public function me(): \CodeIgniter\HTTP\ResponseInterface
    {
        // If we reach here the adminauth filter has already validated the session
        return $this->json(['authenticated' => true]);
    }
}
