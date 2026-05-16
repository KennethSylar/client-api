<?php

namespace App\Infrastructure\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SecurityHeaders filter
 *
 * Adds hardened security headers to every response.
 * Register in Config/Filters.php globals → after.
 */
class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) {}

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Content-Type-Options',  'nosniff');
        $response->setHeader('X-Frame-Options',         'DENY');
        $response->setHeader('X-XSS-Protection',        '1; mode=block');
        $response->setHeader('Referrer-Policy',         'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy',      'geolocation=(), microphone=(), camera=()');

        if (ENVIRONMENT === 'production') {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
