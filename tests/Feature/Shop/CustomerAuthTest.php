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
 *
 * Cookie auth tests (Epic A-1):
 * - login/register set jnv_customer_session httpOnly cookie
 * - logout expires cookie
 * - GET /me authenticates via cookie (no Bearer header)
 * - cookie takes precedence over invalid Bearer header
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

    private function withCookie(string $token): static
    {
        service('superglobals')->setCookie('jnv_customer_session', $token);
        return $this;
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

    // ── Cookie auth (Epic A-1) ────────────────────────────────────────

    public function test_login_sets_httponly_cookie(): void
    {
        $this->enableShop();
        $this->seedCustomer(['email' => 'cookie-login@example.com']);

        $result = $this->post('shop/account/login', [
            'email'    => 'cookie-login@example.com',
            'password' => 'secret123',
        ]);
        $result->assertStatus(200);

        $cookie = $result->response()->getCookie('jnv_customer_session');
        $this->assertNotNull($cookie, 'jnv_customer_session cookie must be present on login');
        $this->assertNotEmpty($cookie->getValue(), 'Cookie value must not be empty');
        $this->assertTrue($cookie->isHttpOnly(), 'Cookie must be HttpOnly');
        $this->assertSame('Lax', $cookie->getSameSite(), 'Cookie SameSite must be Lax');
    }

    public function test_register_sets_httponly_cookie(): void
    {
        $this->enableShop();

        $result = $this->post('shop/account/register', [
            'first_name' => 'Cookie',
            'last_name'  => 'Test',
            'email'      => 'cookie-reg@example.com',
            'password'   => 'password123',
        ]);
        $result->assertStatus(200);

        $cookie = $result->response()->getCookie('jnv_customer_session');
        $this->assertNotNull($cookie, 'jnv_customer_session cookie must be present on register');
        $this->assertNotEmpty($cookie->getValue(), 'Cookie value must not be empty');
        $this->assertTrue($cookie->isHttpOnly(), 'Cookie must be HttpOnly');
        $this->assertSame('Lax', $cookie->getSameSite(), 'Cookie SameSite must be Lax');
    }

    public function test_logout_expires_cookie(): void
    {
        $this->enableShop();
        $customer = $this->seedCustomer();
        $token    = $this->seedSession($customer['id']);

        $result = $this->withCookie($token)->post('shop/account/logout', []);
        $result->assertStatus(200);

        $cookie = $result->response()->getCookie('jnv_customer_session');
        $this->assertNotNull($cookie, 'Response must include Set-Cookie to expire it');
        $this->assertSame('', $cookie->getValue(), 'Expired cookie must have empty value');
    }

    public function test_me_authenticates_via_cookie(): void
    {
        $this->enableShop();
        $customer = $this->seedCustomer();
        $token    = $this->seedSession($customer['id']);

        // No Authorization header — only cookie
        $result = $this->withCookie($token)->get('shop/account/me');
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertSame($customer['id'], $data['customer']['id']);
    }

    public function test_me_returns_401_with_invalid_cookie(): void
    {
        $this->enableShop();

        $result = $this->withCookie('not-a-real-token')->get('shop/account/me');
        $result->assertStatus(401);
    }

    public function test_cookie_takes_precedence_over_invalid_bearer(): void
    {
        $this->enableShop();
        $customer = $this->seedCustomer();
        $token    = $this->seedSession($customer['id']);

        // Valid cookie + invalid Bearer — filter should use cookie and succeed
        $result = $this->withCookie($token)
                       ->withHeaders(['Authorization' => 'Bearer invalid-bearer-token'])
                       ->get('shop/account/me');
        $result->assertStatus(200);

        $this->assertSame($customer['id'], $this->json($result)['customer']['id']);
    }

    public function test_login_cookie_token_matches_response_body_token(): void
    {
        $this->enableShop();
        $this->seedCustomer(['email' => 'tokencheck@example.com']);

        $result = $this->post('shop/account/login', [
            'email'    => 'tokencheck@example.com',
            'password' => 'secret123',
        ]);
        $result->assertStatus(200);

        $bodyToken   = $this->json($result)['token'];
        $cookieToken = $result->response()->getCookie('jnv_customer_session')->getValue();

        $this->assertSame($bodyToken, $cookieToken, 'Cookie token must match body token');
    }
}
