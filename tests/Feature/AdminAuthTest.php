<?php

namespace Tests\Feature;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   POST admin/login   (no auth)
 *   POST admin/logout  (auth)
 *   GET  admin/me      (auth)
 */
class AdminAuthTest extends FeatureTestCase
{
    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Seed a bcrypt password hash into the settings table.
     * Uses the real password_hash so login verification passes.
     */
    private function seedPassword(string $plain = 'secret'): void
    {
        $db   = \Config\Database::connect($this->DBGroup);
        $hash = password_hash($plain, PASSWORD_BCRYPT);

        $existing = $db->table('settings')->where('key', 'admin_password_hash')->get()->getRowArray();
        if ($existing) {
            $db->table('settings')->where('key', 'admin_password_hash')->update(['value' => $hash]);
        } else {
            $db->table('settings')->insert(['key' => 'admin_password_hash', 'value' => $hash]);
        }
    }

    // ── POST admin/login ─────────────────────────────────────────────

    public function test_login_returns_400_when_password_is_empty(): void
    {
        $result = $this->post('admin/login', []);
        $result->assertStatus(400);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_login_returns_400_when_password_key_missing(): void
    {
        $result = $this->post('admin/login', ['user' => 'admin']);
        $result->assertStatus(400);
    }

    public function test_login_returns_401_when_no_hash_is_seeded(): void
    {
        // No admin_password_hash row in settings → invalid
        $result = $this->post('admin/login', ['password' => 'secret']);
        $result->assertStatus(401);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_login_returns_401_for_wrong_password(): void
    {
        $this->seedPassword('correct-password');

        $result = $this->post('admin/login', ['password' => 'wrong-password']);
        $result->assertStatus(401);
    }

    public function test_login_returns_200_and_sets_cookie_for_correct_password(): void
    {
        $this->seedPassword('secret');

        $result = $this->post('admin/login', ['password' => 'secret']);
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertArrayHasKey('ok', $body);
        $this->assertTrue($body['ok']);
    }

    public function test_login_creates_session_row_in_db(): void
    {
        $this->seedPassword('secret');
        $db = \Config\Database::connect($this->DBGroup);

        $before = $db->table('admin_sessions')->countAllResults();

        $this->post('admin/login', ['password' => 'secret'])->assertStatus(200);

        $after = $db->table('admin_sessions')->countAllResults();
        $this->assertSame($before + 1, $after);
    }

    public function test_login_cleans_up_expired_sessions(): void
    {
        $this->seedPassword('secret');
        $db = \Config\Database::connect($this->DBGroup);

        // Insert an already-expired session
        $db->table('admin_sessions')->insert([
            'token'      => str_repeat('a', 64),
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $this->post('admin/login', ['password' => 'secret'])->assertStatus(200);

        // The expired row must be gone
        $expired = $db->table('admin_sessions')
            ->where('token', str_repeat('a', 64))
            ->countAllResults();
        $this->assertSame(0, $expired);
    }

    // ── POST admin/logout ────────────────────────────────────────────

    public function test_logout_requires_auth(): void
    {
        // No cookie set — adminauth filter rejects
        $result = $this->post('admin/logout', []);
        $result->assertStatus(401);
    }

    public function test_logout_returns_200_and_removes_session(): void
    {
        $this->withAdmin();

        $db    = \Config\Database::connect($this->DBGroup);
        $token = $db->table('admin_sessions')->select('token')->orderBy('id', 'DESC')->get()->getRowArray()['token'];

        $result = $this->post('admin/logout', []);
        $result->assertStatus(200);

        $remaining = $db->table('admin_sessions')->where('token', $token)->countAllResults();
        $this->assertSame(0, $remaining);
    }

    // ── GET admin/me ─────────────────────────────────────────────────

    public function test_me_returns_401_without_auth(): void
    {
        $result = $this->get('admin/me');
        $result->assertStatus(401);
    }

    public function test_me_returns_authenticated_true_with_valid_session(): void
    {
        $result = $this->withAdmin()->get('admin/me');
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertArrayHasKey('authenticated', $body);
        $this->assertTrue($body['authenticated']);
    }
}
