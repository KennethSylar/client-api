<?php

namespace App\Controllers\Shop;

use App\Application\Shop\Queries\GetProductQuery;
use App\Application\Shop\Queries\ListProductsQuery;
use App\Controllers\BaseController;
use App\Domain\Shop\Product;

class Products extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $page    = max(1, (int) ($this->request->getGet('page')     ?? 1));
        $perPage = min(96, max(1, (int) ($this->request->getGet('per_page') ?? 24)));
        $search  = trim($this->request->getGet('search') ?? '');
        $catSlug = trim($this->request->getGet('category') ?? '');

        // Resolve category slug → id if provided
        $categoryId = null;
        if ($catSlug !== '') {
            $cat = service('categoryRepository')->findAll();
            foreach ($cat as $c) {
                if ($c->slug === $catSlug) {
                    $categoryId = $c->id;
                    break;
                }
            }
        }

        $result = service('listProductsHandler')->handle(new ListProductsQuery(
            page:       $page,
            perPage:    $perPage,
            search:     $search,
            categoryId: $categoryId,
            activeOnly: true,
        ));

        return $this->ok([
            'products'   => array_map([$this, 'formatProduct'], $result->items),
            'pagination' => $result->meta(),
        ]);
    }

    public function show(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $product = service('getProductHandler')->handle(new GetProductQuery(slug: $slug));

        if ($product === null || !$product->active) {
            return $this->notFound('Product not found.');
        }

        return $this->ok(['product' => $this->formatProductFull($product)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function formatProduct(Product $p): array
    {
        return [
            'id'                  => $p->id,
            'slug'                => $p->slug,
            'name'                => $p->name,
            'price'               => $p->price,
            'vat_exempt'          => $p->vatExempt,
            'track_stock'         => $p->trackStock,
            'stock_qty'           => $p->stockQty,
            'low_stock_threshold' => $p->lowStockThreshold,
            'category_id'         => $p->categoryId,
            'category_name'       => $p->categoryName,
            'category_slug'       => $p->categorySlug,
            'in_stock'            => $p->inStock(),
            'low_stock'           => $p->isLowStock(),
            'cover_image'         => $p->coverImage,
        ];
    }

    private function formatProductFull(Product $p): array
    {
        return array_merge($this->formatProduct($p), [
            'description'     => $p->description,
            'landing_content' => $p->landingContent,
            'active'          => $p->active,
            'images'          => array_map(fn($i) => [
                'id'       => $i->id,
                'url'      => $i->url,
                'alt'      => $i->alt,
                'position' => $i->position,
            ], $p->images),
            'variants'        => array_map(fn($v) => [
                'id'               => $v->id,
                'name'             => $v->name,
                'price_adjustment' => $v->priceAdjustment,
                'track_stock'      => $v->trackStock,
                'stock_qty'        => $v->stockQty,
                'in_stock'         => $v->inStock(),
            ], $p->variants),
        ]);
    }
}
