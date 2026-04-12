<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\App;

/**
 * CORS Filter
 *
 * Applied globally (before every request). Reads `app.allowedOrigins`
 * from the App config (settable via .env) and sets appropriate headers.
 * Handles OPTIONS preflight requests immediately with a 200 response.
 */
class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config        = config(App::class);
        $allowedOrigin = $config->allowedOrigins ?? '*';

        // If allowedOrigins is a comma-separated list, check the request origin
        if ($allowedOrigin !== '*' && str_contains($allowedOrigin, ',')) {
            $origins       = array_map('trim', explode(',', $allowedOrigin));
            $requestOrigin = $request->getHeaderLine('Origin');
            $allowedOrigin = in_array($requestOrigin, $origins, true) ? $requestOrigin : $origins[0];
        }

        $response = service('response');
        $response->setHeader('Access-Control-Allow-Origin',      $allowedOrigin);
        $response->setHeader('Access-Control-Allow-Methods',     'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers',     'Content-Type, X-Requested-With, Cookie');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Max-Age',           '86400');

        // Handle OPTIONS preflight — respond immediately
        if ($request->getMethod() === 'options') {
            return $response->setStatusCode(200)->setBody('');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing needed after
    }
}
