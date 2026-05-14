<?php

namespace App\Controllers\Admin\Shop;

use App\Controllers\BaseController;

/**
 * Admin\Shop\Products  (protected)
 *
 * GET    /admin/shop/products          — paginated list (all products, incl. inactive)
 * POST   /admin/shop/products          — create
 * PUT    /admin/shop/products/:id      — update
 * DELETE /admin/shop/products/:id      — delete
 */
class Products extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();

        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 25)));
        $offset  = ($page - 1) * $perPage;
        $search  = trim($this->request->getGet('search') ?? '');
        $catId   = $this->request->getGet('category_id');

        $builder = $db->table('shop_products p')
            ->select('
                p.id, p.slug, p.name, p.price, p.vat_exempt,
                p.track_stock, p.stock_qty, p.low_stock_threshold, p.active,
                p.category_id, c.name AS category_name,
                (SELECT url FROM shop_product_images
                 WHERE product_id = p.id ORDER BY position ASC LIMIT 1) AS cover_image
            ')
            ->join('shop_categories c', 'c.id = p.category_id', 'left');

        if ($search !== '') {
            $builder->groupStart()
                ->like('p.name', $search)
                ->orLike('p.slug', $search)
                ->groupEnd();
        }

        if ($catId !== null) {
            $builder->where('p.category_id', (int) $catId);
        }

        $total = $builder->countAllResults(reset: false);
        $rows  = $builder->orderBy('p.name', 'ASC')->limit($perPage, $offset)->get()->getResultArray();

        foreach ($rows as &$row) {
            $row = $this->castRow($row);
        }

        return $this->ok([
            'products'   => $rows,
            'pagination' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        $name = trim($body['name'] ?? '');

        if ($name === '') {
            return $this->error('name is required.', 400);
        }

        $price = isset($body['price']) ? (float) $body['price'] : 0.00;
        if ($price < 0) {
            return $this->error('price must be >= 0.', 400);
        }

        $db   = \Config\Database::connect();
        $base = $this->slugify($body['slug'] ?? $name);
        $slug = $this->uniqueSlug($db, $base);

        $db->table('shop_products')->insert([
            'name'                => $name,
            'slug'                => $slug,
            'description'         => $body['description']         ?? '',
            'price'               => $price,
            'vat_exempt'          => isset($body['vat_exempt'])   ? (int)(bool)$body['vat_exempt'] : 0,
            'track_stock'         => isset($body['track_stock'])  ? (int)(bool)$body['track_stock'] : 1,
            'stock_qty'           => isset($body['stock_qty'])    ? (int)$body['stock_qty'] : 0,
            'low_stock_threshold' => isset($body['low_stock_threshold']) ? (int)$body['low_stock_threshold'] : 5,
            'landing_content'     => isset($body['landing_content']) ? json_encode($body['landing_content']) : null,
            'category_id'         => isset($body['category_id'])  ? (int)$body['category_id'] : null,
            'active'              => isset($body['active'])        ? (int)(bool)$body['active'] : 1,
        ]);

        $id  = (int) $db->insertID();
        $row = $this->fetchFull($db, $id);

        return $this->json(['product' => $row], 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db  = \Config\Database::connect();
        $row = $db->table('shop_products')->where('id', $id)->get()->getRowArray();

        if (!$row) {
            return $this->notFound('Product not found.');
        }

        $body   = $this->jsonBody();
        $update = [];

        if (isset($body['name'])) {
            $name = trim($body['name']);
            if ($name === '') return $this->error('name cannot be empty.', 400);
            $update['name'] = $name;
        }

        if (isset($body['slug'])) {
            $base = $this->slugify($body['slug']);
            $update['slug'] = $this->uniqueSlug($db, $base, $id);
        } elseif (isset($body['name']) && $body['name'] !== $row['name']) {
            // Auto-reslug when name changes and no explicit slug given
            $update['slug'] = $this->uniqueSlug($db, $this->slugify($body['name']), $id);
        }

        foreach (['description'] as $field) {
            if (isset($body[$field])) $update[$field] = $body[$field];
        }

        if (isset($body['price'])) {
            $price = (float) $body['price'];
            if ($price < 0) return $this->error('price must be >= 0.', 400);
            $update['price'] = $price;
        }

        foreach (['vat_exempt', 'track_stock', 'active'] as $bool) {
            if (isset($body[$bool])) $update[$bool] = (int)(bool)$body[$bool];
        }

        foreach (['stock_qty', 'low_stock_threshold', 'category_id'] as $int) {
            if (array_key_exists($int, $body)) {
                $update[$int] = $body[$int] !== null ? (int)$body[$int] : null;
            }
        }

        if (array_key_exists('landing_content', $body)) {
            $update['landing_content'] = $body['landing_content'] !== null
                ? json_encode($body['landing_content'])
                : null;
        }

        if (!empty($update)) {
            $db->table('shop_products')->where('id', $id)->update($update);
        }

        return $this->ok(['product' => $this->fetchFull($db, $id)]);
    }

    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db  = \Config\Database::connect();
        $row = $db->table('shop_products')->where('id', $id)->get()->getRowArray();

        if (!$row) {
            return $this->notFound('Product not found.');
        }

        // Images and variants cascade via FK; just delete the product
        $db->table('shop_products')->where('id', $id)->delete();

        return $this->ok();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function fetchFull(\CodeIgniter\Database\BaseConnection $db, int $id): array
    {
        $row = $db->table('shop_products p')
            ->select('p.*, c.name AS category_name, c.slug AS category_slug')
            ->join('shop_categories c', 'c.id = p.category_id', 'left')
            ->where('p.id', $id)
            ->get()->getRowArray();

        $row = $this->castRow($row);

        $row['images'] = array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'url'      => $r['url'],
            'alt'      => $r['alt'],
            'position' => (int) $r['position'],
        ], $db->table('shop_product_images')
              ->where('product_id', $id)
              ->orderBy('position', 'ASC')
              ->get()->getResultArray());

        $row['variants'] = array_map(fn($r) => [
            'id'               => (int)   $r['id'],
            'name'             => $r['name'],
            'price_adjustment' => (float) $r['price_adjustment'],
            'track_stock'      => (bool)  $r['track_stock'],
            'stock_qty'        => (int)   $r['stock_qty'],
            'position'         => (int)   $r['position'],
        ], $db->table('shop_product_variants')
              ->where('product_id', $id)
              ->orderBy('position', 'ASC')
              ->get()->getResultArray());

        return $row;
    }

    private function castRow(array $row): array
    {
        $row['id']                  = (int)   $row['id'];
        $row['price']               = (float) $row['price'];
        $row['vat_exempt']          = (bool)  $row['vat_exempt'];
        $row['track_stock']         = (bool)  $row['track_stock'];
        $row['stock_qty']           = (int)   $row['stock_qty'];
        $row['low_stock_threshold'] = (int)   $row['low_stock_threshold'];
        $row['active']              = (bool)  $row['active'];
        $row['in_stock']            = !$row['track_stock'] || $row['stock_qty'] > 0;
        $row['low_stock']           = $row['track_stock']
                                      && $row['stock_qty'] > 0
                                      && $row['stock_qty'] <= $row['low_stock_threshold'];

        if (isset($row['category_id'])) {
            $row['category_id'] = $row['category_id'] !== null ? (int) $row['category_id'] : null;
        }
        if (array_key_exists('landing_content', $row)) {
            $row['landing_content'] = $row['landing_content']
                ? json_decode($row['landing_content'], true)
                : null;
        }
        return $row;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    private function uniqueSlug(\CodeIgniter\Database\BaseConnection $db, string $base, ?int $excludeId = null): string
    {
        $slug   = $base;
        $suffix = 2;

        while (true) {
            $q = $db->table('shop_products')->where('slug', $slug);
            if ($excludeId !== null) $q->where('id !=', $excludeId);
            if ($q->countAllResults() === 0) return $slug;
            $slug = $base . '-' . $suffix++;
        }
    }
}
