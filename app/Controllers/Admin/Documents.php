<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Admin\Documents
 *
 * Protected CRUD for downloadable documents.
 * Mirrors:
 *   POST   /api/admin/documents
 *   PUT    /api/admin/documents/:id
 *   DELETE /api/admin/documents/:id
 */
class Documents extends BaseController
{
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        if (empty($body['category']) || empty($body['title'])) {
            return $this->error('Category and title are required.', 400);
        }

        $db = \Config\Database::connect();
        $db->table('documents')->insert([
            'category'    => $body['category'],
            'title'       => $body['title'],
            'description' => $body['description'] ?? '',
            'filename'    => $body['filename']    ?? '',
            'file_url'    => $body['file_url']    ?? '',
            'file_size'   => $body['file_size']   ?? '',
            'published'   => isset($body['published']) ? (int) $body['published'] : 1,
        ]);

        return $this->json(['ok' => true, 'id' => $db->insertID()], 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        $db   = \Config\Database::connect();

        $existing = $db->table('documents')->where('id', $id)->get()->getRowArray();
        if (empty($existing)) {
            return $this->notFound("Document #{$id} not found.");
        }

        $db->table('documents')->where('id', $id)->update([
            'category'    => $body['category']    ?? $existing['category'],
            'title'       => $body['title']        ?? $existing['title'],
            'description' => $body['description']  ?? $existing['description'],
            'filename'    => $body['filename']      ?? $existing['filename'],
            'file_url'    => $body['file_url']      ?? $existing['file_url'],
            'file_size'   => $body['file_size']     ?? $existing['file_size'],
            'published'   => isset($body['published']) ? (int) $body['published'] : (int) $existing['published'],
        ]);

        return $this->ok();
    }

    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();
        $db->table('documents')->where('id', $id)->delete();
        return $this->ok();
    }
}
