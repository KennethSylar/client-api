<?php

namespace App\Controllers\Shop;

use App\Controllers\BaseController;

/**
 * Shop\Categories  (public)
 *
 * GET /shop/categories  — all active categories with product count
 */
class Categories extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $db = \Config\Database::connect();

        $rows = $db->query("
            SELECT
                c.id,
                c.slug,
                c.name,
                c.parent_id,
                c.position,
                COUNT(p.id) AS product_count
            FROM shop_categories c
            LEFT JOIN shop_products p
                ON p.category_id = c.id AND p.active = 1
            GROUP BY c.id
            ORDER BY c.position ASC, c.name ASC
        ")->getResultArray();

        // Cast types
        foreach ($rows as &$row) {
            $row['id']            = (int) $row['id'];
            $row['parent_id']     = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $row['position']      = (int) $row['position'];
            $row['product_count'] = (int) $row['product_count'];
        }

        return $this->ok(['categories' => $rows]);
    }
}
