<?php

namespace App\Infrastructure\Http\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    public function initController(
        RequestInterface  $request,
        ResponseInterface $response,
        LoggerInterface   $logger
    ): void {
        parent::initController($request, $response, $logger);
        helper(['cookie', 'url']);
    }

    // ----------------------------------------------------------------
    // JSON response helpers
    // ----------------------------------------------------------------

    protected function ok(array $data = ['ok' => true]): ResponseInterface
    {
        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/json')
            ->setBody(json_encode($data));
    }

    protected function json(array $data, int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setBody(json_encode($data));
    }

    protected function error(string $message, int $status = 400): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setBody(json_encode(['error' => $message]));
    }

    protected function notFound(string $message = 'Not found'): ResponseInterface
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->error($message, 401);
    }

    /**
     * Returns a 503 response if the shop is disabled, null otherwise.
     * Usage: if ($off = $this->shopOffline()) return $off;
     */
    protected function shopOffline(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if (service('settingsRepository')->get('shop_enabled') !== '1') {
            return $this->error('Shop is currently unavailable.', 503);
        }
        return null;
    }

    /**
     * Returns true if the rate limit for the given key has been exceeded.
     * Uses CI4 cache. Key is typically "action_ip" or "action_email".
     *
     * Usage:
     *   if ($this->rateLimited('admin_login_' . $ip, 5, 300)) return $this->tooManyRequests();
     */
    protected function rateLimited(string $key, int $max, int $windowSeconds): bool
    {
        $cacheKey = 'rl_' . md5($key);
        $hits     = (int) (cache($cacheKey) ?? 0);

        if ($hits >= $max) {
            return true;
        }

        // Save incremented count; keep TTL at window length from first hit
        if ($hits === 0) {
            cache()->save($cacheKey, 1, $windowSeconds);
        } else {
            // Preserve remaining TTL by re-saving with the same window
            cache()->save($cacheKey, $hits + 1, $windowSeconds);
        }

        return false;
    }

    protected function tooManyRequests(string $message = 'Too many requests. Please try again later.'): ResponseInterface
    {
        return $this->response
            ->setStatusCode(429)
            ->setContentType('application/json')
            ->setBody(json_encode(['error' => $message]));
    }

    /**
     * Parse JSON request body, supporting both raw JSON and form-encoded.
     */
    protected function jsonBody(): array
    {
        $raw = $this->request->getBody();
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        // Fallback: form fields (for PUT requests some clients send form data)
        return $this->request->getRawInput() ?? [];
    }
}
