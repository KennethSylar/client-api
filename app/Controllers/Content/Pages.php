<?php

namespace App\Controllers\Content;

use App\Controllers\BaseController;

/**
 * Content\Pages
 *
 * Public endpoint: retrieves CMS page data by slug.
 * Mirrors: GET /api/content/page/:slug
 */
class Pages extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('pages')
                   ->select('slug, data, updated_at')
                   ->orderBy('slug', 'ASC')
                   ->get()
                   ->getResultArray();

        $pages = array_map(function ($row) {
            $data  = is_string($row['data']) ? (json_decode($row['data'], true) ?? []) : ($row['data'] ?? []);
            return [
                'slug'       => $row['slug'],
                'title'      => $data['title']      ?? $row['slug'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);

        return $this->json($pages);
    }

    public function show(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        $db  = \Config\Database::connect();
        $row = $db->table('pages')
                  ->where('slug', $slug)
                  ->get()
                  ->getRowArray();

        if (empty($row)) {
            return $this->notFound("Page '{$slug}' not found.");
        }

        // The `data` column stores JSON — decode it
        $data = $row['data'];
        if (is_string($data)) {
            $data = json_decode($data, true) ?? [];
        }

        // Ensure `content` key is always an object
        if (!isset($data['content']) || !is_array($data['content'])) {
            $data['content'] = (object) [];
        }

        return $this->json($data);
    }
}
