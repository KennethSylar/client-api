<?php

namespace Tests\Feature;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   PUT admin/settings
 */
class AdminSettingsTest extends FeatureTestCase
{
    // ── Auth guard ───────────────────────────────────────────────────

    public function test_update_requires_auth(): void
    {
        $result = $this->put('admin/settings', ['site_name' => 'Test']);
        $result->assertStatus(401);
    }

    // ── Validation ───────────────────────────────────────────────────

    public function test_update_returns_400_for_empty_body(): void
    {
        $result = $this->withAdmin()->put('admin/settings', []);
        $result->assertStatus(400);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    // ── Arbitrary key-value storage ──────────────────────────────────

    public function test_update_stores_arbitrary_key_value(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        $result = $this->withAdmin()->put('admin/settings', ['site_name' => 'My Site']);
        $result->assertStatus(200);

        $row = $db->table('settings')->where('key', 'site_name')->get()->getRowArray();
        $this->assertNotEmpty($row);
        $this->assertSame('My Site', $row['value']);
    }

    public function test_update_stores_multiple_keys_in_one_request(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        $this->withAdmin()->put('admin/settings', [
            'contact_email' => 'hello@example.com',
            'contact_phone' => '+27 11 000 0000',
        ])->assertStatus(200);

        $email = $db->table('settings')->where('key', 'contact_email')->get()->getRowArray();
        $phone = $db->table('settings')->where('key', 'contact_phone')->get()->getRowArray();

        $this->assertSame('hello@example.com', $email['value']);
        $this->assertSame('+27 11 000 0000', $phone['value']);
    }

    public function test_update_overwrites_existing_key(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('settings')->insert(['key' => 'site_name', 'value' => 'Old Name']);

        $this->withAdmin()->put('admin/settings', ['site_name' => 'New Name'])->assertStatus(200);

        $row = $db->table('settings')->where('key', 'site_name')->get()->getRowArray();
        $this->assertSame('New Name', $row['value']);

        // Only one row should exist for this key
        $count = $db->table('settings')->where('key', 'site_name')->countAllResults();
        $this->assertSame(1, $count);
    }

    public function test_update_inserts_new_key_that_did_not_exist(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        $key = 'brand_new_setting_' . time();
        $this->withAdmin()->put('admin/settings', [$key => 'value123'])->assertStatus(200);

        $row = $db->table('settings')->where('key', $key)->get()->getRowArray();
        $this->assertNotEmpty($row);
        $this->assertSame('value123', $row['value']);
    }

    // ── admin_password_hash special handling ─────────────────────────

    public function test_update_hashes_admin_password(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        $this->withAdmin()->put('admin/settings', ['admin_password_hash' => 'my-plain-password'])->assertStatus(200);

        $row = $db->table('settings')->where('key', 'admin_password_hash')->get()->getRowArray();
        $this->assertNotEmpty($row);

        // Value must NOT be the plain text
        $this->assertNotSame('my-plain-password', $row['value']);

        // Value must be a valid bcrypt hash that verifies the plain text
        $this->assertTrue(password_verify('my-plain-password', $row['value']));
    }

    public function test_update_password_hash_starts_with_bcrypt_prefix(): void
    {
        $this->withAdmin()->put('admin/settings', ['admin_password_hash' => 'testpass'])->assertStatus(200);

        $db  = \Config\Database::connect($this->DBGroup);
        $row = $db->table('settings')->where('key', 'admin_password_hash')->get()->getRowArray();

        $this->assertStringStartsWith('$2y$', $row['value']);
    }

    public function test_update_skips_hashing_when_password_value_is_empty(): void
    {
        // Seeding an existing hash first
        $existingHash = password_hash('oldpassword', PASSWORD_BCRYPT);
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('settings')->where('key', 'admin_password_hash')->delete();
        $db->table('settings')->insert(['key' => 'admin_password_hash', 'value' => $existingHash]);

        // Sending empty string for admin_password_hash
        $this->withAdmin()->put('admin/settings', ['admin_password_hash' => ''])->assertStatus(200);

        $row = $db->table('settings')->where('key', 'admin_password_hash')->get()->getRowArray();
        // Empty string should be stored as-is (no hashing of empty value)
        $this->assertSame('', $row['value']);
    }

    // ── accreditations array → JSON string ───────────────────────────

    public function test_update_serialises_accreditations_array_as_json(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        $accreditations = [
            ['name' => 'ISO 9001', 'logo' => 'https://cdn.example.com/iso.png'],
            ['name' => 'SABS',     'logo' => 'https://cdn.example.com/sabs.png'],
        ];

        $this->withAdmin()->put('admin/settings', ['accreditations' => $accreditations])->assertStatus(200);

        $row = $db->table('settings')->where('key', 'accreditations')->get()->getRowArray();
        $this->assertNotEmpty($row);

        $decoded = json_decode($row['value'], true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertSame('ISO 9001', $decoded[0]['name']);
        $this->assertSame('SABS', $decoded[1]['name']);
    }

    public function test_update_stores_empty_accreditations_array_as_json(): void
    {
        $db = \Config\Database::connect($this->DBGroup);

        $this->withAdmin()->put('admin/settings', ['accreditations' => []])->assertStatus(200);

        $row     = $db->table('settings')->where('key', 'accreditations')->get()->getRowArray();
        $decoded = json_decode($row['value'], true);
        $this->assertIsArray($decoded);
        $this->assertCount(0, $decoded);
    }

    public function test_update_returns_200_ok(): void
    {
        $result = $this->withAdmin()->put('admin/settings', ['foo' => 'bar']);
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertTrue($body['ok'] ?? false);
    }
}
