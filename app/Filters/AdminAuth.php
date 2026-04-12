<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AdminAuth Filter
 *
 * Validates the `jnv_admin_session` cookie against the
 * admin_sessions table. Returns 401 JSON if invalid or expired.
 */
class AdminAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */
        $token = $request->getCookie('jnv_admin_session');

        if (empty($token)) {
            return $this->unauthorized('Unauthorized');
        }

        $db  = \Config\Database::connect();
        $row = $db->table('admin_sessions')
                  ->where('token', $token)
                  ->where('expires_at >', date('Y-m-d H:i:s'))
                  ->get()
                  ->getRowArray();

        if (empty($row)) {
            // Clear stale cookie via response service
            service('response')->deleteCookie('jnv_admin_session');
            return $this->unauthorized('Session expired');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing needed after
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setContentType('application/json')
            ->setBody(json_encode(['error' => $message]));
    }
}
