<?php

namespace App\Controllers\Shop;

use App\Controllers\BaseController;

/**
 * Shop\Products  (public)
 *
 * GET /shop/products              — paginated list, filter by category slug, search by name
 * GET /shop/products/:slug        — single product with images and variants
 */
class Products extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $db = \Config\Database::connect();

        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage  = min(96, max(1, (int) ($this->request->getGet('per_page') ?? 24)));
        $offset   = ($page - 1) * $perPage;
        $search   = trim($this->request->getGet('search') ?? '');
        $catSlug  = trim($this->request->getGet('category') ?? '');

        $builder = $db->table('shop_products p')
            ->select('
                p.id, p.slug, p.name, p.price, p.vat_exempt,
                p.track_stock, p.stock_qty, p.low_stock_threshold,
                p.category_id, c.name AS category_name, c.slug AS category_slug,
                (SELECT url FROM shop_product_images
                 WHERE product_id = p.id ORDER BY position ASC LIMIT 1) AS cover_image
            ')
            ->join('shop_categories c', 'c.id = p.category_id', 'left')
            ->where('p.active', 1);

        if ($search !== '') {
            $builder->groupStart()
                ->like('p.name', $search)
                ->orLike('p.description', $search)
                ->groupEnd();
        }

        if ($catSlug !== '') {
            $builder->where('c.slug', $catSlug);
        }

        $total   = $builder->countAllResults(reset: false);
        $rows    = $builder->orderBy('p.name', 'ASC')->limit($perPage, $offset)->get()->getResultArray();

        foreach ($rows as &$row) {
            $row = $this->castProduct($row);
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

    public function show(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $db  = \Config\Database::connect();
        $row = $db->table('shop_products p')
            ->select('
                p.id, p.slug, p.name, p.description, p.price, p.vat_exempt,
                p.track_stock, p.stock_qty, p.low_stock_threshold,
                p.category_id, c.name AS category_name, c.slug AS category_slug,
                p.landing_content, p.active
            ')
            ->join('shop_categories c', 'c.id = p.category_id', 'left')
            ->where('p.slug', $slug)
            ->where('p.active', 1)
            ->get()->getRowArray();

        if (!$row) {
            return $this->notFound('Product not found.');
        }

        $row    = $this->castProduct($row);
        $row['images']   = $this->getImages($db, $row['id']);
        $row['variants'] = $this->getVariants($db, $row['id']);

        return $this->ok(['product' => $row]);
    }

    // ----------------------------------------------------------------
    // Shared helpers
    // ----------------------------------------------------------------

    private function castProduct(array $row): array
    {
        $row['id']                  = (int)  $row['id'];
        $row['price']               = (float) $row['price'];
        $row['vat_exempt']          = (bool) $row['vat_exempt'];
        $row['track_stock']         = (bool) $row['track_stock'];
        $row['stock_qty']           = (int)  $row['stock_qty'];
        $row['low_stock_threshold'] = (int)  $row['low_stock_threshold'];
        $row['in_stock']            = !$row['track_stock'] || $row['stock_qty'] > 0;
        $row['low_stock']           = $row['track_stock']
                                      && $row['stock_qty'] > 0
                                      && $row['stock_qty'] <= $row['low_stock_threshold'];

        if (isset($row['category_id'])) {
            $row['category_id'] = $row['category_id'] !== null ? (int) $row['category_id'] : null;
        }
        if (isset($row['landing_content'])) {
            $row['landing_content'] = $row['landing_content']
                ? json_decode($row['landing_content'], true)
                : null;
        }
        if (isset($row['active'])) {
            $row['active'] = (bool) $row['active'];
        }
        return $row;
    }

    private function getImages(\CodeIgniter\Database\BaseConnection $db, int $productId): array
    {
        $rows = $db->table('shop_product_images')
            ->where('product_id', $productId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'url'      => $r['url'],
            'alt'      => $r['alt'],
            'position' => (int) $r['position'],
        ], $rows);
    }

    private function getVariants(\CodeIgniter\Database\BaseConnection $db, int $productId): array
    {
        $rows = $db->table('shop_product_variants')
            ->where('product_id', $productId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        return array_map(fn($r) => [
            'id'               => (int)   $r['id'],
            'name'             => $r['name'],
            'price_adjustment' => (float) $r['price_adjustment'],
            'track_stock'      => (bool)  $r['track_stock'],
            'stock_qty'        => (int)   $r['stock_qty'],
            'in_stock'         => !(bool) $r['track_stock'] || (int) $r['stock_qty'] > 0,
        ], $rows);
    }
}
