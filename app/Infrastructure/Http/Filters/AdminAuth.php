<?php

namespace App\Infrastructure\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuth implements FilterInterface
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
