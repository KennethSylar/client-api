<?php

namespace App\Controllers\Shop;

use App\Controllers\BaseController;

class CustomerAuth extends BaseController
{
    /**
     * POST /shop/account/register
     *
     * Body: { "first_name", "last_name", "email", "password" }
     */
    public function register(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $body = $this->jsonBody();

        foreach (['first_name','last_name','email','password'] as $field) {
            if (empty($body[$field])) {
                return $this->error("Missing required field: {$field}", 400);
            }
        }

        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address.', 400);
        }

        if (strlen($body['password']) < 8) {
            return $this->error('Password must be at least 8 characters.', 400);
        }

        $db    = \Config\Database::connect();
        $email = strtolower(trim($body['email']));

        if ($db->table('shop_customers')->where('email', $email)->countAllResults()) {
            return $this->error('An account with this email already exists.', 409);
        }

        $db->table('shop_customers')->insert([
            'email'          => $email,
            'first_name'     => trim($body['first_name']),
            'last_name'      => trim($body['last_name']),
            'password_hash'  => password_hash($body['password'], PASSWORD_BCRYPT),
            'email_verified' => 0,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $customerId = (int)$db->insertID();

        return $this->ok([
            'customer' => $this->customerPayload($db, $customerId),
            'token'    => $this->createSession($db, $customerId),
        ]);
    }

    /**
     * POST /shop/account/login
     *
     * Body: { "email", "password" }
     */
    public function login(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $body  = $this->jsonBody();
        $email = strtolower(trim($body['email'] ?? ''));
        $pass  = $body['password'] ?? '';

        if ($email === '' || $pass === '') {
            return $this->error('Email and password are required.', 400);
        }

        $db       = \Config\Database::connect();
        $customer = $db->table('shop_customers')->where('email', $email)->get()->getRowArray();

        if (!$customer || !password_verify($pass, $customer['password_hash'] ?? '')) {
            return $this->error('Invalid credentials.', 401);
        }

        $customerId = (int)$customer['id'];

        // Link any guest orders placed with this email before account existed
        $this->linkGuestOrders($db, $customerId, $email);

        return $this->ok([
            'customer' => $this->customerPayload($db, $customerId),
            'token'    => $this->createSession($db, $customerId),
        ]);
    }

    /**
     * POST /shop/account/logout
     */
    public function logout(): \CodeIgniter\HTTP\ResponseInterface
    {
        $token = $this->getBearerToken();
        if ($token) {
            $db = \Config\Database::connect();
            $db->table('shop_customer_sessions')->where('token', $token)->delete();
        }
        return $this->ok();
    }

    /**
     * GET /shop/account/me
     * Requires valid bearer token.
     */
    public function me(): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        return $this->ok(['customer' => $customer]);
    }

    /**
     * PUT /shop/account/me
     * Update name and/or password.
     */
    public function update(): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $body = $this->jsonBody();
        $db   = \Config\Database::connect();

        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        if (!empty($body['first_name'])) $updates['first_name'] = trim($body['first_name']);
        if (!empty($body['last_name']))  $updates['last_name']  = trim($body['last_name']);
        if (!empty($body['phone']))      $updates['phone']      = trim($body['phone']);

        if (!empty($body['new_password'])) {
            if (strlen($body['new_password']) < 8) {
                return $this->error('Password must be at least 8 characters.', 400);
            }
            if (empty($body['current_password'])) {
                return $this->error('Current password is required to change password.', 400);
            }
            $row = $db->table('shop_customers')->where('id', $customer['id'])->get()->getRowArray();
            if (!password_verify($body['current_password'], $row['password_hash'] ?? '')) {
                return $this->error('Current password is incorrect.', 401);
            }
            $updates['password_hash'] = password_hash($body['new_password'], PASSWORD_BCRYPT);
        }

        $db->table('shop_customers')->where('id', $customer['id'])->update($updates);

        return $this->ok(['customer' => $this->customerPayload($db, $customer['id'])]);
    }

    /**
     * GET /shop/account/orders
     */
    public function orders(): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $db = \Config\Database::connect();

        $page    = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = 10;

        $builder = $db->table('shop_orders')
            ->where('customer_id', $customer['id'])
            ->orderBy('created_at', 'DESC');

        $total  = $builder->countAllResults(false);
        $rows   = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return $this->ok([
            'data' => array_map(fn($o) => [
                'id'           => (int)$o['id'],
                'token'        => $o['token'],
                'status'       => $o['status'],
                'total_cents'  => (int)$o['total_cents'],
                'currency'     => $o['currency'],
                'created_at'   => $o['created_at'],
            ], $rows),
            'meta' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int)ceil($total / $perPage),
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    protected function requireCustomer(): array|\CodeIgniter\HTTP\ResponseInterface
    {
        $token = $this->getBearerToken();
        if (!$token) return $this->unauthorized('Authentication required.');

        $db      = \Config\Database::connect();
        $session = $db->table('shop_customer_sessions')
            ->where('token', $token)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get()->getRowArray();

        if (!$session) return $this->unauthorized('Session expired or invalid.');

        return $this->customerPayload($db, (int)$session['customer_id']);
    }

    private function customerPayload(\CodeIgniter\Database\BaseConnection $db, int $id): array
    {
        $row = $db->table('shop_customers')->where('id', $id)->get()->getRowArray();
        return [
            'id'         => (int)$row['id'],
            'email'      => $row['email'],
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'phone'      => $row['phone'],
        ];
    }

    private function createSession(\CodeIgniter\Database\BaseConnection $db, int $customerId): string
    {
        $token = bin2hex(random_bytes(32));
        $db->table('shop_customer_sessions')->insert([
            'customer_id' => $customerId,
            'token'       => $token,
            'expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    private function linkGuestOrders(\CodeIgniter\Database\BaseConnection $db, int $customerId, string $email): void
    {
        // Assign any unlinked orders with matching email to this customer
        $db->table('shop_orders')
            ->where('email', $email)
            ->where('customer_id IS NULL', null, false)
            ->update(['customer_id' => $customerId]);
    }

    private function getBearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
