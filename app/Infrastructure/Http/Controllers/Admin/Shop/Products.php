<?php

namespace App\Infrastructure\Http\Controllers\Admin\Shop;

use App\Application\Shop\Commands\CreateProductCommand;
use App\Application\Shop\Commands\DeleteProductCommand;
use App\Application\Shop\Commands\UpdateProductCommand;
use App\Application\Shop\Queries\GetProductQuery;
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

    public function show(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $product = service('getProductHandler')->handle(new GetProductQuery(id: $id));

        if (!$product) {
            return $this->notFound('Product not found.');
        }

        return $this->ok(['product' => $this->formatProductFull($product)]);
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

    public function export(): \CodeIgniter\HTTP\ResponseInterface
    {
        $search = trim($this->request->getGet('search') ?? '');
        $catId  = $this->request->getGet('category_id');

        $result = service('listProductsHandler')->handle(new ListProductsQuery(
            page:       1,
            perPage:    10000,
            search:     $search,
            categoryId: $catId !== null ? (int) $catId : null,
        ));

        // Template mode — return empty CSV with headers + one example row
        if ($this->request->getGet('template') === '1') {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, ['ID', 'Name', 'Slug', 'Price', 'VAT Exempt', 'Category', 'Track Stock', 'Stock Qty', 'Low Stock Threshold', 'Active']);
            fputcsv($out, ['', 'Example Product', 'example-product', '99.99', 'no', '', 'yes', '10', '3', 'yes']);
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);
            return $this->response
                ->setStatusCode(200)
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="products-import-template.csv"')
                ->setBody("\xEF\xBB\xBF" . $csv);
        }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['ID', 'Name', 'Slug', 'Price', 'VAT Exempt', 'Category', 'Track Stock', 'Stock Qty', 'Low Stock Threshold', 'Active']);

        foreach ($result->items as $p) {
            fputcsv($out, [
                $p->id,
                $p->name,
                $p->slug,
                number_format($p->price, 2, '.', ''),
                $p->vatExempt  ? 'yes' : 'no',
                $p->categoryName ?? '',
                $p->trackStock ? 'yes' : 'no',
                $p->stockQty,
                $p->lowStockThreshold,
                $p->active ? 'yes' : 'no',
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="products-' . date('Y-m-d') . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }

    public function import(): \CodeIgniter\HTTP\ResponseInterface
    {
        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid()) {
            return $this->error('No valid file uploaded.', 400);
        }

        if (strtolower($file->getClientExtension()) !== 'csv') {
            return $this->error('File must be a CSV.', 400);
        }

        $handle = fopen($file->getTempName(), 'r');
        $header = fgetcsv($handle); // skip header row

        // Normalise header names
        $header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

        $created = 0;
        $updated = 0;
        $errors  = [];
        $row     = 1;

        while (($cols = fgetcsv($handle)) !== false) {
            $row++;
            if (count($cols) < 2) continue;

            $data = array_combine($header, array_pad($cols, count($header), ''));

            $name = trim($data['name'] ?? '');
            if (!$name) {
                $errors[] = "Row {$row}: missing name — skipped.";
                continue;
            }

            $price = (float) str_replace(',', '.', $data['price'] ?? '0');
            $id    = isset($data['id']) && is_numeric($data['id']) ? (int) $data['id'] : null;

            $cmd = new \App\Application\Shop\Commands\UpdateProductCommand(
                id:                 $id ?? 0,
                name:               $name,
                slug:               trim($data['slug'] ?? ''),
                description:        '',
                price:              $price,
                vatExempt:          strtolower(trim($data['vat_exempt'] ?? 'no')) === 'yes',
                trackStock:         strtolower(trim($data['track_stock'] ?? 'no')) === 'yes',
                stockQty:           isset($data['stock_qty']) && is_numeric($data['stock_qty']) ? (int) $data['stock_qty'] : 0,
                lowStockThreshold:  isset($data['low_stock_threshold']) && is_numeric($data['low_stock_threshold']) ? (int) $data['low_stock_threshold'] : 5,
                active:             strtolower(trim($data['active'] ?? 'yes')) !== 'no',
                categoryId:         null,
                landingContent:     null,
            );

            try {
                if ($id) {
                    $existing = service('getProductHandler')->handle(new \App\Application\Shop\Queries\GetProductQuery(id: $id));
                    if ($existing) {
                        service('updateProductHandler')->handle(new \App\Application\Shop\Commands\UpdateProductCommand(
                            id:                $id,
                            name:              $name,
                            price:             $price,
                            slug:              trim($data['slug'] ?? '') ?: null,
                            vatExempt:         strtolower(trim($data['vat_exempt'] ?? 'no')) === 'yes',
                            trackStock:        strtolower(trim($data['track_stock'] ?? 'no')) === 'yes',
                            stockQty:          isset($data['stock_qty']) && is_numeric($data['stock_qty']) ? (int) $data['stock_qty'] : null,
                            lowStockThreshold: isset($data['low_stock_threshold']) && is_numeric($data['low_stock_threshold']) ? (int) $data['low_stock_threshold'] : null,
                            active:            strtolower(trim($data['active'] ?? 'yes')) !== 'no',
                        ));
                        $updated++;
                        continue;
                    }
                }
                service('createProductHandler')->handle(new \App\Application\Shop\Commands\CreateProductCommand(
                    name:              $name,
                    price:             $price,
                    slug:              trim($data['slug'] ?? '') ?: null,
                    vatExempt:         strtolower(trim($data['vat_exempt'] ?? 'no')) === 'yes',
                    trackStock:        strtolower(trim($data['track_stock'] ?? 'no')) === 'yes',
                    stockQty:          isset($data['stock_qty']) && is_numeric($data['stock_qty']) ? (int) $data['stock_qty'] : 0,
                    lowStockThreshold: isset($data['low_stock_threshold']) && is_numeric($data['low_stock_threshold']) ? (int) $data['low_stock_threshold'] : 5,
                    active:            strtolower(trim($data['active'] ?? 'yes')) !== 'no',
                ));
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row {$row} ({$name}): " . $e->getMessage();
            }
        }

        fclose($handle);

        return $this->ok([
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ]);
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
