<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Application\Orders\Commands\LoginCustomerCommand;
use App\Application\Orders\Commands\LogoutCustomerCommand;
use App\Application\Orders\Commands\RegisterCustomerCommand;
use App\Application\Orders\Commands\UpdateCustomerCommand;
use App\Application\Orders\Queries\GetCustomerOrdersQuery;
use App\Domain\Orders\Customer;
use App\Infrastructure\Http\Controllers\BaseController;

class CustomerAuth extends BaseController
{
    public function register(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $body = $this->jsonBody();

        foreach (['first_name', 'last_name', 'email', 'password'] as $field) {
            if (empty($body[$field])) {
                return $this->error("Missing required field: {$field}", 400);
            }
        }

        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address.', 400);
        }

        try {
            [$customer, $token] = service('registerCustomerHandler')->handle(new RegisterCustomerCommand(
                firstName: trim($body['first_name']),
                lastName:  trim($body['last_name']),
                email:     $body['email'],
                password:  $body['password'],
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->ok([
            'customer' => $this->formatCustomer($customer),
            'token'    => $token,
        ]);
    }

    public function login(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $body  = $this->jsonBody();
        $email = trim($body['email'] ?? '');
        $pass  = $body['password'] ?? '';

        if ($email === '' || $pass === '') {
            return $this->error('Email and password are required.', 400);
        }

        try {
            [$customer, $token] = service('loginCustomerHandler')->handle(new LoginCustomerCommand(
                email:    $email,
                password: $pass,
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 401);
        }

        return $this->ok([
            'customer' => $this->formatCustomer($customer),
            'token'    => $token,
        ]);
    }

    public function logout(): \CodeIgniter\HTTP\ResponseInterface
    {
        $token = $this->getBearerToken();
        if ($token) {
            service('logoutCustomerHandler')->handle(new LogoutCustomerCommand($token));
        }
        return $this->ok();
    }

    public function me(): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        return $this->ok(['customer' => $this->formatCustomer($customer)]);
    }

    public function update(): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $body = $this->jsonBody();

        try {
            $updated = service('updateCustomerHandler')->handle(new UpdateCustomerCommand(
                customerId:      $customer->id,
                firstName:       $body['first_name'] ?? null,
                lastName:        $body['last_name']  ?? null,
                phone:           $body['phone']      ?? null,
                currentPassword: $body['current_password'] ?? null,
                newPassword:     $body['new_password']     ?? null,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }

        return $this->ok(['customer' => $this->formatCustomer($updated)]);
    }

    public function orders(): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 10;

        $result = service('getCustomerOrdersHandler')->handle(new GetCustomerOrdersQuery(
            customerId: $customer->id,
            page:       $page,
            perPage:    $perPage,
        ));

        return $this->ok([
            'data' => array_map(fn($o) => [
                'id'          => $o->id,
                'token'       => $o->token,
                'status'      => $o->status->value,
                'total_cents' => $o->total->amountCents,
                'currency'    => $o->currency,
                'created_at'  => $o->createdAt->format('Y-m-d H:i:s'),
            ], $result->items),
            'meta' => $result->meta(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    protected function requireCustomer(): Customer|\CodeIgniter\HTTP\ResponseInterface
    {
        $token = $this->getBearerToken();
        if (!$token) return $this->unauthorized('Authentication required.');

        $customer = service('customerRepository')->findByToken($token);
        if (!$customer) return $this->unauthorized('Session expired or invalid.');

        return $customer;
    }

    private function formatCustomer(Customer $c): array
    {
        return [
            'id'         => $c->id,
            'email'      => $c->email,
            'first_name' => $c->firstName,
            'last_name'  => $c->lastName,
            'phone'      => $c->phone,
        ];
    }

    private function getBearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }
}
