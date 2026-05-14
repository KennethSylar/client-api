<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for customer account endpoints:
 * POST /shop/account/register
 * POST /shop/account/login
 * POST /shop/account/logout
 * GET  /shop/account/me
 * PUT  /shop/account/me
 * GET  /shop/account/orders
 */
class CustomerAuthTest extends FeatureTestCase
{
    private function seedCustomer(array $overrides = []): array
    {
        $db       = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'email'          => 'test-' . uniqid() . '@example.com',
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'password_hash'  => password_hash('secret123', PASSWORD_BCRYPT),
            'email_verified' => 0,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        $data = array_merge($defaults, $overrides);
        $db->table('shop_customers')->insert($data);
        return array_merge($data, ['id' => (int)$db->insertID()]);
    }

    private function seedSession(int $customerId): string
    {
        $db    = \Config\Database::connect($this->DBGroup);
        $token = bin2hex(random_bytes(32));
        $db->table('shop_customer_sessions')->insert([
            'customer_id' => $customerId,
            'token'       => $token,
            'expires_at'  => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    private function withToken(string $token): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
    }

    // ── Guards ───────────────────────────────────────────────────────

    public function test_returns_503_when_shop_disabled(): void
    {
        $this->post('shop/account/register', [])->assertStatus(503);
    }

    // ── Registration ──────────────────────────────────────────────────

    public function test_registers_a_new_customer(): void
    {
        $this->enableShop();

        $result = $this->post('shop/account/register', [
            'first_name' => 'Alice',
            'last_name'  => 'Smith',
            'email'      => 'alice@example.com',
            'password'   => 'password123',
        ]);
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertSame('alice@example.com', $data['customer']['email']);
        $this->assertNotEmpty($data['token']);

        // Password hash is NOT in response
        $this->assertArrayNotHasKey('password_hash', $data['customer']);
    }

    public function test_rejects_duplicate_email(): void
    {
        $this->enableShop();
        $this->seedCustomer(['email' => 'existing@example.com']);

        $result = $this->post('shop/account/register', [
            'first_name' => 'Bob',
            'last_name'  => 'Jones',
            'email'      => 'existing@example.com',
            'password'   => 'password123',
        ]);
        $result->assertStatus(409);
    }

    public function test_rejects_short_password(): void
    {
        $this->enableShop();

        $result = $this->post('shop/account/register', [
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'test@example.com',
            'password'   => 'short',
        ]);
        $result->assertStatus(400);
    }

    // ── Login ─────────────────────────────────────────────────────────

    public function test_login_returns_token(): void
    {
        $this->enableShop();
        $customer = $this->seedCustomer(['email' => 'login@example.com']);

        $result = $this->post('shop/account/login', [
            'email'    => 'login@example.com',
            'password' => 'secret123',
        ]);
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertNotEmpty($data['token']);
        $this->assertSame('login@example.com', $data['customer']['email']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->enableShop();
        $this->seedCustomer(['email' => 'pw@example.com']);

        $result = $this->post('shop/account/login', [
            'email'    => 'pw@example.com',
            'password' => 'wrongpassword',
        ]);
        $result->assertStatus(401);
    }

    public function test_login_links_guest_orders(): void
    {
        $this->enableShop();
        $customer = $this->seedCustomer(['email' => 'guest@example.com']);

        // Seed an unlinked order with same email
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_orders')->insert([
            'token'          => bin2hex(random_bytes(16)),
            'email'          => 'guest@example.com',
            'first_name'     => 'Guest',
            'last_name'      => 'User',
            'address_line1'  => '1 Road',
            'city'           => 'City',
            'postal_code'    => '1234',
            'country'        => 'ZA',
            'subtotal_cents' => 5000,
            'total_cents'    => 5000,
            'currency'       => 'ZAR',
            'status'         => 'paid',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->post('shop/account/login', [
            'email'    => 'guest@example.com',
            'password' => 'secret123',
        ]);

        // Order should now be linked to the customer
        $linked = $db->table('shop_orders')
            ->where('email', 'guest@example.com')
            ->where('customer_id', $customer['id'])
            ->countAllResults();
        $this->assertSame(1, $linked);
    }

    // ── Me ────────────────────────────────────────────────────────────

    public function test_me_returns_customer(): void
    {
        $this->enableShop();
        $customer = $this->seedCustomer();
        $token    = $this->seedSession($customer['id']);

        $result = $this->withToken($token)->get('shop/account/me');
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertSame($customer['id'], $data['customer']['id']);
    }

    public function test_me_returns_401_without_token(): void
    {
        $this->enableShop();
        $this->get('shop/account/me')->assertStatus(401);
    }

    // ── Update ────────────────────────────────────────────────────────

    public function test_update_profile_name(): void
    {
        $this->enableShop();
        $customer = $this->seedCustomer();
        $token    = $this->seedSession($customer['id']);

        $result = $this->withToken($token)->put('shop/account/me', [
            'first_name' => 'Updated',
        ]);
        $result->assertStatus(200);
        $this->assertSame('Updated', $this->json($result)['customer']['first_name']);
    }

    // ── Orders ────────────────────────────────────────────────────────

    public function test_orders_returns_customer_orders(): void
    {
        $this->enableShop();
        $customer = $this->seedCustomer();
        $token    = $this->seedSession($customer['id']);

        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_orders')->insert([
            'token'          => bin2hex(random_bytes(16)),
            'customer_id'    => $customer['id'],
            'email'          => $customer['email'],
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'address_line1'  => '1 Road',
            'city'           => 'City',
            'postal_code'    => '1234',
            'country'        => 'ZA',
            'subtotal_cents' => 10000,
            'total_cents'    => 10000,
            'currency'       => 'ZAR',
            'status'         => 'paid',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withToken($token)->get('shop/account/orders');
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertCount(1, $data['data']);
    }

    public function test_orders_returns_401_without_token(): void
    {
        $this->enableShop();
        $this->get('shop/account/orders')->assertStatus(401);
    }
}
