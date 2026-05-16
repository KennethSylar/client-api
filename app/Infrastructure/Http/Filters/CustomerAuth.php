<?php

namespace App\Infrastructure\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Authenticates shop customers.
 * Reads jnv_customer_session httpOnly cookie first,
 * falls back to Authorization: Bearer header for backward compat.
 * Sets CustomerAuthContext for use by controllers.
 */
class CustomerAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        /** @var \CodeIgniter\HTTP\IncomingRequest $request */

        // 1. httpOnly cookie (preferred)
        $token = $request->getCookie('jnv_customer_session');

        // 2. Bearer header fallback
        if (empty($token)) {
            $header = $request->getHeaderLine('Authorization');
            $token  = str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
        }

        if (empty($token)) {
            return $this->unauthorized('Authentication required.');
        }

        $customer = service('customerRepository')->findByToken($token);

        if (!$customer) {
            service('response')->deleteCookie('jnv_customer_session');
            return $this->unauthorized('Session expired or invalid.');
        }

        CustomerAuthContext::set($customer);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}

    private function unauthorized(string $message): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setContentType('application/json')
            ->setBody(json_encode(['error' => $message]));
    }
}
