<?php

namespace Tests\Feature;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   POST   admin/pages
 *   PUT    admin/pages/:slug
 *   DELETE admin/pages/:slug
 */
class AdminPagesTest extends FeatureTestCase
{
    // ── Helpers ──────────────────────────────────────────────────────

    private function seedPage(string $slug, string $title = 'Test Page'): void
    {
        $db   = \Config\Database::connect($this->DBGroup);
        $data = json_encode(['title' => $title, 'body' => '', 'eyebrow' => '', 'image' => '', 'seoTitle' => '', 'seoDescription' => '', 'content' => ['html' => '']]);
        $db->table('pages')->insert([
            'slug'       => $slug,
            'data'       => $data,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Auth guard ───────────────────────────────────────────────────

    public function test_create_requires_auth(): void
    {
        $result = $this->post('admin/pages', ['slug' => 'test-page']);
        $result->assertStatus(401);
    }

    public function test_update_requires_auth(): void
    {
        $result = $this->put('admin/pages/home', ['title' => 'Home']);
        $result->assertStatus(401);
    }

    public function test_delete_requires_auth(): void
    {
        $result = $this->delete('admin/pages/some-page');
        $result->assertStatus(401);
    }

    // ── POST admin/pages ─────────────────────────────────────────────

    public function test_create_returns_422_for_empty_slug(): void
    {
        $result = $this->withAdmin()->post('admin/pages', ['slug' => '']);
        $result->assertStatus(422);
    }

    public function test_create_returns_422_for_invalid_slug_with_uppercase(): void
    {
        $result = $this->withAdmin()->post('admin/pages', ['slug' => 'My-Page']);
        $result->assertStatus(422);
    }

    public function test_create_returns_422_for_slug_with_spaces(): void
    {
        $result = $this->withAdmin()->post('admin/pages', ['slug' => 'my page']);
        $result->assertStatus(422);
    }

    public function test_create_returns_422_for_slug_with_leading_hyphen(): void
    {
        $result = $this->withAdmin()->post('admin/pages', ['slug' => '-my-page']);
        $result->assertStatus(422);
    }

    public function test_create_returns_422_for_slug_with_trailing_hyphen(): void
    {
        $result = $this->withAdmin()->post('admin/pages', ['slug' => 'my-page-']);
        $result->assertStatus(422);
    }

    public function test_create_returns_409_for_duplicate_slug(): void
    {
        $this->seedPage('existing-page');

        $result = $this->withAdmin()->post('admin/pages', ['slug' => 'existing-page']);
        $result->assertStatus(409);

        $body = $this->json($result);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_create_returns_201_with_slug_for_valid_input(): void
    {
        $result = $this->withAdmin()->post('admin/pages', [
            'slug'           => 'new-page',
            'title'          => 'New Page',
            'eyebrow'        => 'Eyebrow text',
            'body'           => 'Some body content',
            'seoTitle'       => 'SEO Title',
            'seoDescription' => 'SEO description',
        ]);
        $result->assertStatus(201);

        $body = $this->json($result);
        $this->assertArrayHasKey('slug', $body);
        $this->assertSame('new-page', $body['slug']);
    }

    public function test_create_persists_page_to_db(): void
    {
        $this->withAdmin()->post('admin/pages', ['slug' => 'my-new-page', 'title' => 'My New Page']);

        $db  = \Config\Database::connect($this->DBGroup);
        $row = $db->table('pages')->where('slug', 'my-new-page')->get()->getRowArray();

        $this->assertNotEmpty($row);
        $data = json_decode($row['data'], true);
        $this->assertSame('My New Page', $data['title']);
    }

    public function test_create_accepts_single_word_slug(): void
    {
        $result = $this->withAdmin()->post('admin/pages', ['slug' => 'services']);
        $result->assertStatus(201);
        $this->assertSame('services', $this->json($result)['slug']);
    }

    public function test_create_accepts_alphanumeric_slug(): void
    {
        $result = $this->withAdmin()->post('admin/pages', ['slug' => 'page2025']);
        $result->assertStatus(201);
    }

    // ── PUT admin/pages/:slug ────────────────────────────────────────

    public function test_update_creates_page_when_slug_does_not_exist(): void
    {
        $result = $this->withAdmin()->put('admin/pages/brand-new', [
            'title' => 'Brand New Page',
            'body'  => 'Content here',
        ]);
        $result->assertStatus(200);

        $db  = \Config\Database::connect($this->DBGroup);
        $row = $db->table('pages')->where('slug', 'brand-new')->get()->getRowArray();
        $this->assertNotEmpty($row);
    }

    public function test_update_updates_existing_page(): void
    {
        $this->seedPage('about', 'Old About Title');

        $result = $this->withAdmin()->put('admin/pages/about', [
            'title' => 'Updated About Title',
            'body'  => 'Updated body',
        ]);
        $result->assertStatus(200);

        $db   = \Config\Database::connect($this->DBGroup);
        $row  = $db->table('pages')->where('slug', 'about')->get()->getRowArray();
        $data = json_decode($row['data'], true);
        $this->assertSame('Updated About Title', $data['title']);
        $this->assertSame('Updated body', $data['body']);
    }

    public function test_update_stores_all_fields_in_data_blob(): void
    {
        $result = $this->withAdmin()->put('admin/pages/contact', [
            'title'          => 'Contact Us',
            'eyebrow'        => 'Get in Touch',
            'body'           => 'Body text',
            'image'          => 'https://cdn.example.com/img.jpg',
            'seoTitle'       => 'Contact — Site',
            'seoDescription' => 'Contact page description',
        ]);
        $result->assertStatus(200);

        $db   = \Config\Database::connect($this->DBGroup);
        $row  = $db->table('pages')->where('slug', 'contact')->get()->getRowArray();
        $data = json_decode($row['data'], true);

        $this->assertSame('Contact Us', $data['title']);
        $this->assertSame('Get in Touch', $data['eyebrow']);
        $this->assertSame('https://cdn.example.com/img.jpg', $data['image']);
        $this->assertSame('Contact — Site', $data['seoTitle']);
        $this->assertSame('Contact page description', $data['seoDescription']);
    }

    public function test_update_twice_only_one_db_row_exists(): void
    {
        $this->withAdmin()->put('admin/pages/faq', ['title' => 'FAQ v1']);
        $this->withAdmin()->put('admin/pages/faq', ['title' => 'FAQ v2']);

        $db    = \Config\Database::connect($this->DBGroup);
        $count = $db->table('pages')->where('slug', 'faq')->countAllResults();
        $this->assertSame(1, $count);

        $row  = $db->table('pages')->where('slug', 'faq')->get()->getRowArray();
        $data = json_decode($row['data'], true);
        $this->assertSame('FAQ v2', $data['title']);
    }

    // ── DELETE admin/pages/:slug ─────────────────────────────────────

    public function test_delete_returns_403_for_home(): void
    {
        $result = $this->withAdmin()->delete('admin/pages/home');
        $result->assertStatus(403);
    }

    public function test_delete_returns_403_for_about(): void
    {
        $result = $this->withAdmin()->delete('admin/pages/about');
        $result->assertStatus(403);
    }

    public function test_delete_returns_403_for_training(): void
    {
        $result = $this->withAdmin()->delete('admin/pages/training');
        $result->assertStatus(403);
    }

    public function test_delete_returns_403_for_compliance(): void
    {
        $result = $this->withAdmin()->delete('admin/pages/compliance');
        $result->assertStatus(403);
    }

    public function test_delete_returns_403_for_contact(): void
    {
        $result = $this->withAdmin()->delete('admin/pages/contact');
        $result->assertStatus(403);
    }

    public function test_delete_returns_200_for_custom_page(): void
    {
        $this->seedPage('custom-page');

        $result = $this->withAdmin()->delete('admin/pages/custom-page');
        $result->assertStatus(200);
    }

    public function test_delete_removes_page_from_db(): void
    {
        $this->seedPage('to-be-deleted');

        $this->withAdmin()->delete('admin/pages/to-be-deleted')->assertStatus(200);

        $db    = \Config\Database::connect($this->DBGroup);
        $count = $db->table('pages')->where('slug', 'to-be-deleted')->countAllResults();
        $this->assertSame(0, $count);
    }

    public function test_delete_returns_200_for_non_existent_page(): void
    {
        // DELETE is idempotent — deleting a slug that doesn't exist is still OK
        $result = $this->withAdmin()->delete('admin/pages/ghost-page');
        $result->assertStatus(200);
    }
}
