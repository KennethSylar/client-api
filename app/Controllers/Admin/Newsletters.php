<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Admin\Newsletters
 *
 * Protected CRUD for newsletters.
 * Mirrors:
 *   POST   /api/admin/newsletters
 *   PUT    /api/admin/newsletters/:id
 *   DELETE /api/admin/newsletters/:id
 */
class Newsletters extends BaseController
{
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        if (empty($body['issue']) || empty($body['title'])) {
            return $this->error('Issue and title are required.', 400);
        }

        $db = \Config\Database::connect();
        $db->table('newsletters')->insert([
            'issue'          => $body['issue'],
            'title'          => $body['title'],
            'description'    => $body['description']    ?? '',
            'filename'       => $body['filename']       ?? '',
            'file_url'       => $body['file_url']       ?? '',
            'file_size'      => $body['file_size']      ?? '',
            'published_date' => $body['published_date'] ?? null,
            'published'      => isset($body['published']) ? (int) $body['published'] : 1,
        ]);

        return $this->json(['ok' => true, 'id' => $db->insertID()], 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        $db   = \Config\Database::connect();

        $existing = $db->table('newsletters')->where('id', $id)->get()->getRowArray();
        if (empty($existing)) {
            return $this->notFound("Newsletter #{$id} not found.");
        }

        // COALESCE pattern: preserve existing values for fields not provided
        $db->table('newsletters')->where('id', $id)->update([
            'issue'          => $body['issue']          ?? $existing['issue'],
            'title'          => $body['title']          ?? $existing['title'],
            'description'    => $body['description']    ?? $existing['description'],
            'filename'       => $body['filename']       ?? $existing['filename'],
            'file_url'       => $body['file_url']       ?? $existing['file_url'],
            'file_size'      => $body['file_size']      ?? $existing['file_size'],
            'published_date' => $body['published_date'] ?? $existing['published_date'],
            'published'      => isset($body['published']) ? (int) $body['published'] : (int) $existing['published'],
        ]);

        return $this->ok();
    }

    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();
        $db->table('newsletters')->where('id', $id)->delete();
        return $this->ok();
    }
}
