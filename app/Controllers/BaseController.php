<?php

namespace App\Controllers;

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
