<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Shop\Product;
use App\Domain\Shop\ProductFilter;
use App\Domain\Shop\ProductImage;
use App\Domain\Shop\ProductRepositoryInterface;
use App\Domain\Shop\ProductVariant;
use App\Domain\Shared\PaginatedResult;

class MySqlProductRepository extends AbstractMysqlRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?Product
    {
        $row = $this->db->table('shop_products p')
            ->select('p.*, c.name AS category_name, c.slug AS category_slug')
            ->join('shop_categories c', 'c.id = p.category_id', 'left')
            ->where('p.id', $id)
            ->get()->getRowArray();

        if (!$row) return null;

        $product           = Product::fromArray($row);
        $product->images   = $this->findImages($id);
        $product->variants = $this->findVariants($id);

        return $product;
    }

    public function findBySlug(string $slug): ?Product
    {
        $row = $this->db->table('shop_products p')
            ->select('p.*, c.name AS category_name, c.slug AS category_slug')
            ->join('shop_categories c', 'c.id = p.category_id', 'left')
            ->where('p.slug', $slug)
            ->get()->getRowArray();

        if (!$row) return null;

        $product           = Product::fromArray($row);
        $product->images   = $this->findImages((int) $row['id']);
        $product->variants = $this->findVariants((int) $row['id']);

        return $product;
    }

    public function findAll(ProductFilter $filter): PaginatedResult
    {
        $builder = $this->db->table('shop_products p')
            ->select('
                p.id, p.slug, p.name, p.price, p.vat_exempt,
                p.track_stock, p.stock_qty, p.low_stock_threshold, p.active,
                p.category_id, c.name AS category_name, c.slug AS category_slug,
                (SELECT url FROM shop_product_images
                 WHERE product_id = p.id ORDER BY position ASC LIMIT 1) AS cover_image
            ')
            ->join('shop_categories c', 'c.id = p.category_id', 'left');

        if ($filter->activeOnly) {
            $builder->where('p.active', 1);
        }

        if ($filter->search !== '') {
            $builder->groupStart()
                ->like('p.name', $filter->search)
                ->orLike('p.slug', $filter->search)
                ->groupEnd();
        }

        if ($filter->categoryId !== null) {
            $builder->where('p.category_id', $filter->categoryId);
        }

        $builder->orderBy('p.name', 'ASC');

        $result = $this->paginate($builder, $filter->page, $filter->perPage);

        return new PaginatedResult(
            items:   array_map(fn($r) => Product::fromArray($r), $result->items),
            total:   $result->total,
            page:    $result->page,
            perPage: $result->perPage,
        );
    }

    public function save(Product $product): Product
    {
        $payload = [
            'name'                => $product->name,
            'slug'                => $product->slug,
            'description'         => $product->description,
            'price'               => $product->price,
            'vat_exempt'          => (int) $product->vatExempt,
            'track_stock'         => (int) $product->trackStock,
            'stock_qty'           => $product->stockQty,
            'low_stock_threshold' => $product->lowStockThreshold,
            'landing_content'     => $product->landingContent !== null
                ? json_encode($product->landingContent)
                : null,
            'category_id'         => $product->categoryId,
            'active'              => (int) $product->active,
        ];

        if ($product->id === 0) {
            $payload['slug'] = $this->uniqueSlug('shop_products', $product->slug ?: $this->slugify($product->name));
            $this->db->table('shop_products')->insert($payload);
            $id = (int) $this->db->insertID();
        } else {
            $this->db->table('shop_products')->where('id', $product->id)->update($payload);
            $id = $product->id;
        }

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        // Images and variants cascade via FK
        $this->db->table('shop_products')->where('id', $id)->delete();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $q = $this->db->table('shop_products')->where('slug', $slug);
        if ($excludeId !== null) {
            $q->where('id !=', $excludeId);
        }
        return $q->countAllResults() > 0;
    }

    public function stampLowStockAlert(int $productId): void
    {
        $this->db->table('shop_products')
            ->where('id', $productId)
            ->update(['low_stock_alerted_at' => $this->now()]);
    }

    // ── Image sub-aggregate ───────────────────────────────────────────

    public function findImages(int $productId): array
    {
        $rows = $this->db->table('shop_product_images')
            ->where('product_id', $productId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        return array_map(fn($r) => ProductImage::fromArray($r), $rows);
    }

    public function addImage(ProductImage $image): ProductImage
    {
        // Default position: after the last existing image
        if ($image->position < 0) {
            $max = $this->db->table('shop_product_images')
                ->selectMax('position')
                ->where('product_id', $image->productId)
                ->get()->getRowArray();
            $position = (int) ($max['position'] ?? -1) + 1;
        } else {
            $position = $image->position;
        }

        $this->db->table('shop_product_images')->insert([
            'product_id' => $image->productId,
            'url'        => $image->url,
            'alt'        => $image->alt,
            'position'   => $position,
        ]);

        $id  = (int) $this->db->insertID();
        $row = $this->db->table('shop_product_images')->where('id', $id)->get()->getRowArray();
        return ProductImage::fromArray($row);
    }

    public function deleteImage(int $imageId, int $productId): void
    {
        $this->db->table('shop_product_images')
            ->where('id', $imageId)
            ->where('product_id', $productId)
            ->delete();

        // Re-pack positions to close the gap (0, 1, 2, ...)
        $remaining = $this->db->table('shop_product_images')
            ->where('product_id', $productId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        foreach ($remaining as $i => $img) {
            if ((int) $img['position'] !== $i) {
                $this->db->table('shop_product_images')
                    ->where('id', $img['id'])
                    ->update(['position' => $i]);
            }
        }
    }

    public function reorderImages(int $productId, array $positions): void
    {
        foreach ($positions as $imageId => $position) {
            $this->db->table('shop_product_images')
                ->where('id', (int) $imageId)
                ->where('product_id', $productId)
                ->update(['position' => (int) $position]);
        }
    }

    // ── Variant sub-aggregate ─────────────────────────────────────────

    public function findVariants(int $productId): array
    {
        $rows = $this->db->table('shop_product_variants')
            ->where('product_id', $productId)
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        return array_map(fn($r) => ProductVariant::fromArray($r), $rows);
    }

    public function findVariantById(int $variantId, int $productId): ?ProductVariant
    {
        $row = $this->db->table('shop_product_variants')
            ->where('id', $variantId)
            ->where('product_id', $productId)
            ->get()->getRowArray();

        return $row ? ProductVariant::fromArray($row) : null;
    }
}
