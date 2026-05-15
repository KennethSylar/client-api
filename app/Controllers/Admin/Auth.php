<?php

namespace App\Controllers\Admin;

use App\Application\Core\Commands\AdminLoginCommand;
use App\Controllers\BaseController;
use Config\App;

class Auth extends BaseController
{
    public function login(): \CodeIgniter\HTTP\ResponseInterface
    {
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

        $isHttps = ENVIRONMENT === 'production' && str_starts_with(config(App::class)->baseURL, 'https');
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
