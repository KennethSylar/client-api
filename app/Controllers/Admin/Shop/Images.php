<?php

namespace App\Controllers\Admin\Shop;

use App\Controllers\BaseController;

/**
 * Admin\Shop\Images  (protected)
 *
 * POST   /admin/shop/products/:product_id/images               — store Cloudinary URL
 * DELETE /admin/shop/products/:product_id/images/:image_id     — remove
 * PATCH  /admin/shop/products/:product_id/images/reorder       — bulk reorder
 */
class Images extends BaseController
{
    public function store(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();

        if (!$this->productExists($db, $productId)) {
            return $this->notFound('Product not found.');
        }

        $body = $this->jsonBody();
        $url  = trim($body['url'] ?? '');

        if ($url === '') {
            return $this->error('url is required.', 400);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('url must be a valid URL.', 400);
        }

        // Default position: after the last existing image
        $maxPos = $db->table('shop_product_images')
            ->selectMax('position')
            ->where('product_id', $productId)
            ->get()->getRowArray();

        $position = isset($body['position'])
            ? (int) $body['position']
            : (int) ($maxPos['position'] ?? -1) + 1;

        $db->table('shop_product_images')->insert([
            'product_id' => $productId,
            'url'        => $url,
            'alt'        => trim($body['alt'] ?? ''),
            'position'   => $position,
        ]);

        $id  = (int) $db->insertID();
        $row = $db->table('shop_product_images')->where('id', $id)->get()->getRowArray();

        return $this->json([
            'image' => [
                'id'         => (int) $row['id'],
                'product_id' => (int) $row['product_id'],
                'url'        => $row['url'],
                'alt'        => $row['alt'],
                'position'   => (int) $row['position'],
            ],
        ], 201);
    }

    public function delete(int $productId, int $imageId): \CodeIgniter\HTTP\ResponseInterface
    {
        $db  = \Config\Database::connect();
        $row = $db->table('shop_product_images')
            ->where('id', $imageId)
            ->where('product_id', $productId)
            ->get()->getRowArray();

        if (!$row) {
            return $this->notFound('Image not found.');
        }

        $db->table('shop_product_images')->where('id', $imageId)->delete();

        // Re-pack positions to close the gap (0, 1, 2, ...)
        $remaining = $db->table('shop_product_images')
            ->where('product_id', $productId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        foreach ($remaining as $i => $img) {
            if ((int) $img['position'] !== $i) {
                $db->table('shop_product_images')
                   ->where('id', $img['id'])
                   ->update(['position' => $i]);
            }
        }

        return $this->ok();
    }

    /**
     * PATCH /admin/shop/products/:product_id/images/reorder
     * Body: { "order": [{"id": 3, "position": 0}, {"id": 1, "position": 1}, ...] }
     */
    public function reorder(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();

        if (!$this->productExists($db, $productId)) {
            return $this->notFound('Product not found.');
        }

        $body  = $this->jsonBody();
        $order = $body['order'] ?? [];

        if (!is_array($order) || empty($order)) {
            return $this->error('order array is required.', 400);
        }

        foreach ($order as $item) {
            if (!isset($item['id'], $item['position'])) continue;
            $db->table('shop_product_images')
               ->where('id', (int) $item['id'])
               ->where('product_id', $productId)   // scope to this product only
               ->update(['position' => (int) $item['position']]);
        }

        // Return the updated image list
        $images = $db->table('shop_product_images')
            ->where('product_id', $productId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        return $this->ok([
            'images' => array_map(fn($r) => [
                'id'       => (int) $r['id'],
                'url'      => $r['url'],
                'alt'      => $r['alt'],
                'position' => (int) $r['position'],
            ], $images),
        ]);
    }

    private function productExists(\CodeIgniter\Database\BaseConnection $db, int $id): bool
    {
        return $db->table('shop_products')->where('id', $id)->countAllResults() > 0;
    }
}
