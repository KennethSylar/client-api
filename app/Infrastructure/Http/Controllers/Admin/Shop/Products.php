<?php

namespace App\Infrastructure\Http\Controllers\Admin\Shop;

use App\Application\Shop\Commands\CreateProductCommand;
use App\Application\Shop\Commands\DeleteProductCommand;
use App\Application\Shop\Commands\UpdateProductCommand;
use App\Application\Shop\Queries\ListProductsQuery;
use App\Domain\Shop\Product;
use App\Infrastructure\Http\Controllers\BaseController;

class Products extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $page    = max(1, (int) ($this->request->getGet('page')     ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 25)));
        $search  = trim($this->request->getGet('search') ?? '');
        $catId   = $this->request->getGet('category_id');

        $result = service('listProductsHandler')->handle(new ListProductsQuery(
            page:       $page,
            perPage:    $perPage,
            search:     $search,
            categoryId: $catId !== null ? (int) $catId : null,
        ));

        return $this->ok([
            'products'   => array_map([$this, 'formatProduct'], $result->items),
            'pagination' => $result->meta(),
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

        $product = service('createProductHandler')->handle(new CreateProductCommand(
            name:              $name,
            price:             $price,
            description:       $body['description']         ?? '',
            slug:              isset($body['slug']) ? $body['slug'] : null,
            vatExempt:         (bool) ($body['vat_exempt']           ?? false),
            trackStock:        (bool) ($body['track_stock']          ?? true),
            stockQty:          (int)  ($body['stock_qty']            ?? 0),
            lowStockThreshold: (int)  ($body['low_stock_threshold']  ?? 5),
            categoryId:        isset($body['category_id']) ? (int) $body['category_id'] : null,
            active:            (bool) ($body['active']               ?? true),
            landingContent:    $body['landing_content'] ?? null,
        ));

        return $this->json(['product' => $this->formatProductFull($product)], 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        if (isset($body['name']) && trim($body['name']) === '') {
            return $this->error('name cannot be empty.', 400);
        }

        if (isset($body['price']) && (float) $body['price'] < 0) {
            return $this->error('price must be >= 0.', 400);
        }

        try {
            $product = service('updateProductHandler')->handle(new UpdateProductCommand(
                id:                $id,
                name:              $body['name']        ?? null,
                price:             isset($body['price']) ? (float) $body['price'] : null,
                description:       $body['description'] ?? null,
                slug:              $body['slug']        ?? null,
                vatExempt:         isset($body['vat_exempt'])          ? (bool) $body['vat_exempt']          : null,
                trackStock:        isset($body['track_stock'])         ? (bool) $body['track_stock']         : null,
                stockQty:          isset($body['stock_qty'])           ? (int)  $body['stock_qty']           : null,
                lowStockThreshold: isset($body['low_stock_threshold']) ? (int)  $body['low_stock_threshold'] : null,
                setCategoryId:     array_key_exists('category_id', $body),
                categoryId:        array_key_exists('category_id', $body) && $body['category_id'] !== null
                                       ? (int) $body['category_id'] : null,
                active:            isset($body['active']) ? (bool) $body['active'] : null,
                setLandingContent: array_key_exists('landing_content', $body),
                landingContent:    $body['landing_content'] ?? null,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->ok(['product' => $this->formatProductFull($product)]);
    }

    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            service('deleteProductHandler')->handle(new DeleteProductCommand($id));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->ok();
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
            'active'              => $p->active,
            'in_stock'            => $p->inStock(),
            'low_stock'           => $p->isLowStock(),
            'category_id'         => $p->categoryId,
            'category_name'       => $p->categoryName,
            'cover_image'         => $p->coverImage,
        ];
    }

    private function formatProductFull(Product $p): array
    {
        return array_merge($this->formatProduct($p), [
            'description'     => $p->description,
            'category_slug'   => $p->categorySlug,
            'landing_content' => $p->landingContent,
            'images'          => array_map(fn($i) => $i->toArray(), $p->images),
            'variants'        => array_map(fn($v) => $v->toArray(), $p->variants),
        ]);
    }
}
