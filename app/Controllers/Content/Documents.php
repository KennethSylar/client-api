<?php

namespace App\Controllers\Content;

use App\Controllers\BaseController;

/**
 * Content\Documents
 *
 * Public endpoint: lists published documents ordered by created_at ASC.
 * Mirrors: GET /api/content/documents
 */
class Documents extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('documents')
                   ->where('published', 1)
                   ->orderBy('created_at', 'ASC')
                   ->get()
                   ->getResultArray();

        foreach ($rows as &$row) {
            $row['published'] = (int) $row['published'];
        }

        return $this->json($rows);
    }
}
