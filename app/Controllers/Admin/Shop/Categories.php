<?php

namespace App\Controllers\Admin\Shop;

use App\Controllers\BaseController;

/**
 * Admin\Shop\Categories  (protected)
 *
 * POST   /admin/shop/categories          — create
 * PUT    /admin/shop/categories/:id      — update
 * DELETE /admin/shop/categories/:id      — delete
 * PATCH  /admin/shop/categories/reorder  — bulk reorder
 */
class Categories extends BaseController
{
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        $name = trim($body['name'] ?? '');

        if ($name === '') {
            return $this->error('name is required.', 400);
        }

        $db   = \Config\Database::connect();
        $slug = $this->uniqueSlug($db, $this->slugify($name));

        $db->table('shop_categories')->insert([
            'name'      => $name,
            'slug'      => $slug,
            'parent_id' => isset($body['parent_id']) ? (int) $body['parent_id'] : null,
            'position'  => isset($body['position'])  ? (int) $body['position']  : 0,
        ]);

        $id  = (int) $db->insertID();
        $row = $db->table('shop_categories')->where('id', $id)->get()->getRowArray();

        return $this->json(['category' => $this->cast($row)], 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db  = \Config\Database::connect();
        $row = $db->table('shop_categories')->where('id', $id)->get()->getRowArray();

        if (!$row) {
            return $this->notFound('Category not found.');
        }

        $body   = $this->jsonBody();
        $update = [];

        if (isset($body['name'])) {
            $name = trim($body['name']);
            if ($name === '') {
                return $this->error('name cannot be empty.', 400);
            }
            $update['name'] = $name;
            // Re-slug only if name changed
            if ($name !== $row['name']) {
                $update['slug'] = $this->uniqueSlug($db, $this->slugify($name), $id);
            }
        }

        if (array_key_exists('parent_id', $body)) {
            $parentId = $body['parent_id'] !== null ? (int) $body['parent_id'] : null;
            // Prevent self-reference
            if ($parentId === $id) {
                return $this->error('A category cannot be its own parent.', 400);
            }
            $update['parent_id'] = $parentId;
        }

        if (isset($body['position'])) {
            $update['position'] = (int) $body['position'];
        }

        if (!empty($update)) {
            $db->table('shop_categories')->where('id', $id)->update($update);
        }

        $updated = $db->table('shop_categories')->where('id', $id)->get()->getRowArray();

        return $this->ok(['category' => $this->cast($updated)]);
    }

    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db  = \Config\Database::connect();
        $row = $db->table('shop_categories')->where('id', $id)->get()->getRowArray();

        if (!$row) {
            return $this->notFound('Category not found.');
        }

        // Reassign child categories to this category's parent (or null)
        $db->table('shop_categories')
           ->where('parent_id', $id)
           ->update(['parent_id' => $row['parent_id']]);

        // Unlink products (set category_id = null, not delete)
        $db->table('shop_products')
           ->where('category_id', $id)
           ->update(['category_id' => null]);

        $db->table('shop_categories')->where('id', $id)->delete();

        return $this->ok();
    }

    /**
     * PATCH /admin/shop/categories/reorder
     * Body: { "order": [{"id": 1, "position": 0}, ...] }
     */
    public function reorder(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body  = $this->jsonBody();
        $order = $body['order'] ?? [];

        if (!is_array($order) || empty($order)) {
            return $this->error('order array is required.', 400);
        }

        $db = \Config\Database::connect();

        foreach ($order as $item) {
            if (!isset($item['id'], $item['position'])) continue;
            $db->table('shop_categories')
               ->where('id', (int) $item['id'])
               ->update(['position' => (int) $item['position']]);
        }

        return $this->ok();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    private function uniqueSlug(\CodeIgniter\Database\BaseConnection $db, string $base, ?int $excludeId = null): string
    {
        $slug      = $base;
        $suffix    = 2;

        while (true) {
            $query = $db->table('shop_categories')->where('slug', $slug);
            if ($excludeId !== null) {
                $query->where('id !=', $excludeId);
            }
            if ($query->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $suffix++;
        }
    }

    private function cast(array $row): array
    {
        $row['id']        = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $row['position']  = (int) $row['position'];
        return $row;
    }
}
