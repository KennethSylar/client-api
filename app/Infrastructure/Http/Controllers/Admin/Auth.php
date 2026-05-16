<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Core\Commands\AdminLoginCommand;
use App\Infrastructure\Http\Controllers\BaseController;

class Auth extends BaseController
{
    public function login(): \CodeIgniter\HTTP\ResponseInterface
    {
        $ip = $this->request->getIPAddress();

        // 10 attempts per 15 minutes per IP
        if ($this->rateLimited("admin_login_{$ip}", 10, 900)) {
            log_message('warning', "Admin login rate limit exceeded from {$ip}");
            return $this->tooManyRequests('Too many login attempts. Please try again in 15 minutes.');
        }

        $body     = $this->jsonBody();
        $password = $body['password'] ?? '';

        if (empty($password)) {
            return $this->error('Password is required.', 400);
        }

        try {
            $token = service('adminLoginHandler')->handle(new AdminLoginCommand($password));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 401);
        }

        set_cookie([
            'name'     => 'jnv_admin_session',
            'value'    => $token,
            'expire'   => 86400,
            'secure'   => (ENVIRONMENT === 'production'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        return $this->ok();
    }

    public function logout(): \CodeIgniter\HTTP\ResponseInterface
    {
        $token = get_cookie('jnv_admin_session');
        if (!empty($token)) {
            service('adminSessionRepository')->delete($token);
        }
        delete_cookie('jnv_admin_session');
        return $this->ok();
    }

    public function me(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->json(['authenticated' => true]);
    }
}
