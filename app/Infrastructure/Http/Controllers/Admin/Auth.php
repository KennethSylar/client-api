<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Core\Commands\AdminLoginCommand;
use App\Infrastructure\Http\Controllers\BaseController;
use App\Infrastructure\Http\Filters\AdminAuthContext;

class Auth extends BaseController
{
    public function login(): \CodeIgniter\HTTP\ResponseInterface
    {
        $ip = $this->request->getIPAddress();

        // 10 attempts per 15 minutes per IP
        if ($this->rateLimited("admin_login_ip:{$ip}", 10, 900)) {
            log_message('warning', "Admin login rate limit exceeded from {$ip}");
            return $this->tooManyRequests('Too many login attempts. Please try again in 15 minutes.');
        }

        $body  = $this->jsonBody();
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('A valid email address is required.', 400);
        }

        if (empty($password)) {
            return $this->error('Password is required.', 400);
        }

        // 5 attempts per email per 15 minutes — credential stuffing protection
        $emailKey = md5(strtolower($email));
        if ($this->rateLimited("admin_login_email:{$emailKey}", 5, 900)) {
            return $this->tooManyRequests('Too many login attempts. Please try again in 15 minutes.');
        }

        try {
            $result = service('adminLoginHandler')->handle(new AdminLoginCommand($email, $password));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 403);
        }

        set_cookie([
            'name'     => 'jnv_admin_session',
            'value'    => $result['token'],
            'expire'   => 86400,
            'secure'   => (ENVIRONMENT === 'production'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        return $this->ok(['role' => $result['role'], 'name' => $result['name']]);
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
        $ctx  = AdminAuthContext::get();
        $user = service('adminUserRepository')->findById($ctx['user_id']);

        if ($user === null) {
            return $this->error('User not found.', 404);
        }

        return $this->ok([
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);
    }
}
