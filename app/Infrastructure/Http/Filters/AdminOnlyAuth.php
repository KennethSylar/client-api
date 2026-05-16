<?php

namespace App\Infrastructure\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restricts access to users with the 'admin' role only.
 * Must be chained after AdminAuth (which sets AdminAuthContext).
 * Used on routes that shop_admin should not access.
 */
class AdminOnlyAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        $token = $request->getCookie('jnv_admin_session');

        if (empty($token)) {
            return $this->unauthorized('Unauthorized');
        }

        $session = service('adminSessionRepository')->find($token);

        if ($session === null) {
            service('response')->deleteCookie('jnv_admin_session');
            return $this->unauthorized('Session expired');
        }

        AdminAuthContext::set([
            'user_id' => (int) $session['user_id'],
            'role'    => $session['role'],
        ]);

        if ($session['role'] !== 'admin') {
            return $this->forbidden('Insufficient permissions');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}

    private function unauthorized(string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setContentType('application/json')
            ->setBody(json_encode(['error' => $message]));
    }

    private function forbidden(string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(403)
            ->setContentType('application/json')
            ->setBody(json_encode(['error' => $message]));
    }
}
