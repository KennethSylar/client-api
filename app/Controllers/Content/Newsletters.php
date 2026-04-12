<?php

namespace App\Controllers\Content;

use App\Controllers\BaseController;

/**
 * Content\Newsletters
 *
 * Public endpoint: lists published newsletters ordered by date DESC.
 * Mirrors: GET /api/content/newsletters
 */
class Newsletters extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('newsletters')
                   ->where('published', 1)
                   ->orderBy('published_date', 'DESC')
                   ->get()
                   ->getResultArray();

        // Cast published to int for consistent typing
        foreach ($rows as &$row) {
            $row['published'] = (int) $row['published'];
        }

        return $this->json($rows);
    }
}
