<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Admin\Pages
 *
 * Protected: upserts CMS page data by slug.
 * Mirrors: PUT /api/admin/pages/:slug
 */
class Pages extends BaseController
{
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        $slug = trim($body['slug'] ?? '');

        if (!$slug || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return $this->error('Slug must be lowercase letters, numbers and hyphens (e.g. my-page).', 422);
        }

        $db = \Config\Database::connect();
        if ($db->table('pages')->where('slug', $slug)->countAllResults() > 0) {
            return $this->error("A page with slug '{$slug}' already exists.", 409);
        }

        $data = [
            'eyebrow'        => $body['eyebrow']        ?? '',
            'title'          => $body['title']          ?? '',
            'body'           => $body['body']           ?? '',
            'image'          => $body['image']          ?? '',
            'seoTitle'       => $body['seoTitle']       ?? '',
            'seoDescription' => $body['seoDescription'] ?? '',
            'content'        => ['html' => ''],
        ];

        $db->table('pages')->insert([
            'slug'       => $slug,
            'data'       => json_encode($data),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->json(['slug' => $slug], 201);
    }

    public function delete(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        // Protect the core site pages from deletion
        $protected = ['home', 'about', 'training', 'compliance', 'farm-development', 'projects', 'contact', 'downloads'];
        if (in_array($slug, $protected)) {
            return $this->error("The '{$slug}' page cannot be deleted.", 403);
        }

        $db = \Config\Database::connect();
        $db->table('pages')->where('slug', $slug)->delete();

        return $this->ok();
    }

    public function update(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        // Build the data JSON blob (same structure as the Nuxt SSR handler)
        $data = [
            'eyebrow'        => $body['eyebrow']        ?? '',
            'title'          => $body['title']          ?? '',
            'body'           => $body['body']           ?? '',
            'image'          => $body['image']          ?? '',
            'seoTitle'       => $body['seoTitle']       ?? '',
            'seoDescription' => $body['seoDescription'] ?? '',
            'content'        => $body['content']        ?? [],
        ];

        $db       = \Config\Database::connect();
        $existing = $db->table('pages')->where('slug', $slug)->get()->getRowArray();

        if ($existing) {
            $db->table('pages')->where('slug', $slug)->update([
                'data'       => json_encode($data),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('pages')->insert([
                'slug'       => $slug,
                'data'       => json_encode($data),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->ok();
    }
}
