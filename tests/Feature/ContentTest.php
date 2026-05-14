<?php

namespace Tests\Feature;

use Tests\Support\FeatureTestCase;

/**
 * Tests for public content endpoints:
 *   GET content/pages
 *   GET content/page/:slug
 *   GET content/settings
 */
class ContentTest extends FeatureTestCase
{
    // ── Helpers ──────────────────────────────────────────────────────

    private function seedPage(string $slug, array $data = []): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('pages')->insert([
            'slug'       => $slug,
            'data'       => json_encode(array_merge([
                'title'          => ucfirst(str_replace('-', ' ', $slug)),
                'eyebrow'        => '',
                'body'           => '',
                'image'          => '',
                'seoTitle'       => '',
                'seoDescription' => '',
                'content'        => ['html' => ''],
            ], $data)),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedSetting(string $key, string $value): void
    {
        $db       = \Config\Database::connect($this->DBGroup);
        $existing = $db->table('settings')->where('key', $key)->get()->getRowArray();
        if ($existing) {
            $db->table('settings')->where('key', $key)->update(['value' => $value]);
        } else {
            $db->table('settings')->insert(['key' => $key, 'value' => $value]);
        }
    }

    // ── GET content/pages ────────────────────────────────────────────

    public function test_pages_index_returns_200(): void
    {
        $result = $this->get('content/pages');
        $result->assertStatus(200);
    }

    public function test_pages_index_returns_empty_array_when_no_pages(): void
    {
        $result = $this->get('content/pages');
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertIsArray($body);
        $this->assertCount(0, $body);
    }

    public function test_pages_index_returns_list_of_pages(): void
    {
        $this->seedPage('home', ['title' => 'Home']);
        $this->seedPage('about', ['title' => 'About Us']);

        $result = $this->get('content/pages');
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertCount(2, $body);
    }

    public function test_pages_index_returns_slug_title_and_updated_at(): void
    {
        $this->seedPage('home', ['title' => 'Home Page']);

        $body = $this->json($this->get('content/pages'));
        $page = $body[0];

        $this->assertArrayHasKey('slug', $page);
        $this->assertArrayHasKey('title', $page);
        $this->assertArrayHasKey('updated_at', $page);
        $this->assertSame('home', $page['slug']);
        $this->assertSame('Home Page', $page['title']);
    }

    public function test_pages_index_returns_slug_as_title_when_data_has_no_title(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('pages')->insert([
            'slug'       => 'no-title-page',
            'data'       => json_encode(['body' => 'Some content']), // no 'title' key
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $body  = $this->json($this->get('content/pages'));
        $slugs = array_column($body, 'slug');
        $idx   = array_search('no-title-page', $slugs);

        $this->assertNotFalse($idx);
        $this->assertSame('no-title-page', $body[$idx]['title']);
    }

    public function test_pages_index_is_ordered_by_slug_asc(): void
    {
        $this->seedPage('zebra');
        $this->seedPage('alpha');
        $this->seedPage('mango');

        $body  = $this->json($this->get('content/pages'));
        $slugs = array_column($body, 'slug');

        $this->assertSame('alpha', $slugs[0]);
        $this->assertSame('mango', $slugs[1]);
        $this->assertSame('zebra', $slugs[2]);
    }

    // ── GET content/page/:slug ───────────────────────────────────────

    public function test_page_show_returns_404_for_unknown_slug(): void
    {
        $result = $this->get('content/page/does-not-exist');
        $result->assertStatus(404);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_page_show_returns_data_for_known_slug(): void
    {
        $this->seedPage('about', ['title' => 'About Us', 'body' => 'We are a team.']);

        $result = $this->get('content/page/about');
        $result->assertStatus(200);

        $body = $this->json($result);
        $this->assertSame('About Us', $body['title']);
        $this->assertSame('We are a team.', $body['body']);
    }

    public function test_page_show_always_includes_content_key(): void
    {
        // Page stored without a 'content' key
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('pages')->insert([
            'slug'       => 'minimal',
            'data'       => json_encode(['title' => 'Minimal']),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $body = $this->json($this->get('content/page/minimal'));
        $this->assertArrayHasKey('content', $body);
    }

    public function test_page_show_returns_all_data_fields(): void
    {
        $this->seedPage('full-page', [
            'title'          => 'Full Page',
            'eyebrow'        => 'Sub heading',
            'body'           => 'Body content',
            'image'          => 'https://cdn.example.com/img.jpg',
            'seoTitle'       => 'SEO Title',
            'seoDescription' => 'SEO Desc',
        ]);

        $body = $this->json($this->get('content/page/full-page'));

        $this->assertSame('Full Page', $body['title']);
        $this->assertSame('Sub heading', $body['eyebrow']);
        $this->assertSame('Body content', $body['body']);
        $this->assertSame('https://cdn.example.com/img.jpg', $body['image']);
        $this->assertSame('SEO Title', $body['seoTitle']);
        $this->assertSame('SEO Desc', $body['seoDescription']);
    }

    // ── GET content/settings ─────────────────────────────────────────

    public function test_settings_index_returns_200(): void
    {
        $result = $this->get('content/settings');
        $result->assertStatus(200);
    }

    public function test_settings_index_returns_object(): void
    {
        $body = $this->json($this->get('content/settings'));
        $this->assertIsArray($body);
    }

    public function test_settings_index_does_not_expose_admin_password_hash(): void
    {
        $db   = \Config\Database::connect($this->DBGroup);
        $hash = password_hash('secret', PASSWORD_BCRYPT);
        $existing = $db->table('settings')->where('key', 'admin_password_hash')->get()->getRowArray();
        if ($existing) {
            $db->table('settings')->where('key', 'admin_password_hash')->update(['value' => $hash]);
        } else {
            $db->table('settings')->insert(['key' => 'admin_password_hash', 'value' => $hash]);
        }

        $body = $this->json($this->get('content/settings'));

        $this->assertArrayNotHasKey('admin_password_hash', $body);
    }

    public function test_settings_index_returns_all_other_settings(): void
    {
        $this->seedSetting('site_name', 'My Website');
        $this->seedSetting('contact_email', 'hello@example.com');

        $body = $this->json($this->get('content/settings'));

        $this->assertArrayHasKey('site_name', $body);
        $this->assertSame('My Website', $body['site_name']);
        $this->assertArrayHasKey('contact_email', $body);
        $this->assertSame('hello@example.com', $body['contact_email']);
    }

    public function test_settings_index_decodes_accreditations_json_as_array(): void
    {
        $accreditations = [
            ['name' => 'ISO 9001', 'logo' => 'https://cdn.example.com/iso.png'],
        ];
        $this->seedSetting('accreditations', json_encode($accreditations));

        $body = $this->json($this->get('content/settings'));

        $this->assertArrayHasKey('accreditations', $body);
        $this->assertIsArray($body['accreditations']);
        $this->assertCount(1, $body['accreditations']);
        $this->assertSame('ISO 9001', $body['accreditations'][0]['name']);
    }

    public function test_settings_index_returns_empty_array_for_invalid_accreditations_json(): void
    {
        $this->seedSetting('accreditations', 'not-valid-json');

        $body = $this->json($this->get('content/settings'));

        $this->assertArrayHasKey('accreditations', $body);
        $this->assertIsArray($body['accreditations']);
        $this->assertCount(0, $body['accreditations']);
    }
}
