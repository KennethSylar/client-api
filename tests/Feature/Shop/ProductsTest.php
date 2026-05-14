<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   GET  /shop/products
 *   GET  /shop/products/:slug
 *   GET  /admin/shop/products
 *   POST /admin/shop/products
 *   PUT  /admin/shop/products/:id
 *   DEL  /admin/shop/products/:id
 */
class ProductsTest extends FeatureTestCase
{
    private function seedProduct(array $overrides = []): array
    {
        $db = \Config\Database::connect($this->DBGroup);
        $defaults = [
            'slug'        => 'test-product',
            'name'        => 'Test Product',
            'price'       => '99.99',
            'stock_qty'   => 10,
            'track_stock' => 1,
            'active'      => 1,
        ];
        $db->table('shop_products')->insert(array_merge($defaults, $overrides));
        return array_merge($defaults, $overrides, ['id' => (int) $db->insertID()]);
    }

    // ── Public: GET /shop/products ──────────────────────────────────

    public function test_public_list_returns_503_when_shop_disabled(): void
    {
        $this->get('shop/products')->assertStatus(503);
    }

    public function test_public_list_returns_active_products_only(): void
    {
        $this->enableShop();
        $this->seedProduct(['slug' => 'active',   'name' => 'Active',   'active' => 1]);
        $this->seedProduct(['slug' => 'inactive', 'name' => 'Inactive', 'active' => 0]);

        $data = $this->json($this->get('shop/products'));
        $this->assertCount(1, $data['products']);
        $this->assertSame('Active', $data['products'][0]['name']);
    }

    public function test_public_list_pagination_envelope(): void
    {
        $this->enableShop();
        $result = $this->get('shop/products');
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertArrayHasKey('total', $data['pagination']);
        $this->assertArrayHasKey('pages', $data['pagination']);
    }

    public function test_public_list_filters_by_category_slug(): void
    {
        $this->enableShop();
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'tools', 'name' => 'Tools', 'position' => 0]);
        $catId = (int) $db->insertID();

        $this->seedProduct(['slug' => 'hammer',  'name' => 'Hammer',  'category_id' => $catId]);
        $this->seedProduct(['slug' => 'screw',   'name' => 'Screw',   'category_id' => null]);

        $data = $this->json($this->get('shop/products?category=tools'));
        $this->assertCount(1, $data['products']);
        $this->assertSame('Hammer', $data['products'][0]['name']);
    }

    public function test_public_list_searches_by_name(): void
    {
        $this->enableShop();
        $this->seedProduct(['slug' => 'blue-widget', 'name' => 'Blue Widget']);
        $this->seedProduct(['slug' => 'red-widget',  'name' => 'Red Widget']);
        $this->seedProduct(['slug' => 'other-thing', 'name' => 'Other Thing']);

        $data = $this->json($this->get('shop/products?search=widget'));
        $this->assertCount(2, $data['products']);
    }

    public function test_public_list_includes_in_stock_flag(): void
    {
        $this->enableShop();
        $this->seedProduct(['slug' => 'in',  'stock_qty' => 5,  'track_stock' => 1]);
        $this->seedProduct(['slug' => 'out', 'stock_qty' => 0,  'track_stock' => 1]);
        $this->seedProduct(['slug' => 'untracked', 'stock_qty' => 0, 'track_stock' => 0]);

        $products = $this->json($this->get('shop/products'))['products'];
        $bySlug = array_column($products, null, 'slug');

        $this->assertTrue($bySlug['in']['in_stock']);
        $this->assertFalse($bySlug['out']['in_stock']);
        $this->assertTrue($bySlug['untracked']['in_stock']); // untracked always in stock
    }

    // ── Public: GET /shop/products/:slug ────────────────────────────

    public function test_public_show_returns_503_when_shop_disabled(): void
    {
        $this->get('shop/products/test-product')->assertStatus(503);
    }

    public function test_public_show_returns_404_for_unknown_slug(): void
    {
        $this->enableShop();
        $this->get('shop/products/does-not-exist')->assertStatus(404);
    }

    public function test_public_show_returns_404_for_inactive_product(): void
    {
        $this->enableShop();
        $this->seedProduct(['active' => 0]);
        $this->get('shop/products/test-product')->assertStatus(404);
    }

    public function test_public_show_returns_product_with_images_and_variants(): void
    {
        $this->enableShop();
        $prod = $this->seedProduct();
        $db   = \Config\Database::connect($this->DBGroup);

        $db->table('shop_product_images')->insert([
            'product_id' => $prod['id'], 'url' => 'https://cdn.example.com/img.jpg', 'alt' => 'Alt', 'position' => 0,
        ]);
        $db->table('shop_product_variants')->insert([
            'product_id' => $prod['id'], 'name' => 'Large', 'price_adjustment' => 10.00, 'stock_qty' => 3, 'position' => 0,
        ]);

        $result  = $this->get('shop/products/test-product');
        $result->assertStatus(200);
        $product = $this->json($result)['product'];

        $this->assertSame('Test Product', $product['name']);
        $this->assertCount(1, $product['images']);
        $this->assertCount(1, $product['variants']);
        $this->assertSame('https://cdn.example.com/img.jpg', $product['images'][0]['url']);
        $this->assertSame('Large', $product['variants'][0]['name']);
        $this->assertSame(10.0, (float) $product['variants'][0]['price_adjustment']);
    }

    // ── Admin: GET /admin/shop/products ─────────────────────────────

    public function test_admin_list_requires_auth(): void
    {
        $this->get('admin/shop/products')->assertStatus(401);
    }

    public function test_admin_list_includes_inactive_products(): void
    {
        $this->seedProduct(['slug' => 'active',   'active' => 1]);
        $this->seedProduct(['slug' => 'inactive', 'active' => 0]);

        $data = $this->json($this->withAdmin()->get('admin/shop/products'));
        $this->assertSame(2, $data['pagination']['total']);
    }

    // ── Admin: POST /admin/shop/products ────────────────────────────

    public function test_admin_create_requires_auth(): void
    {
        $this->post('admin/shop/products', ['name' => 'X'])->assertStatus(401);
    }

    public function test_admin_create_validates_name(): void
    {
        $this->withAdmin()->post('admin/shop/products', [])->assertStatus(400);
    }

    public function test_admin_create_rejects_negative_price(): void
    {
        $result = $this->withAdmin()->post('admin/shop/products', ['name' => 'X', 'price' => -5]);
        $result->assertStatus(400);
    }

    public function test_admin_create_returns_201(): void
    {
        $result = $this->withAdmin()->post('admin/shop/products', [
            'name'        => 'New Product',
            'price'       => 149.99,
            'description' => 'A great product',
            'stock_qty'   => 20,
        ]);
        $result->assertStatus(201);

        $prod = $this->json($result)['product'];
        $this->assertSame('New Product', $prod['name']);
        $this->assertSame('new-product', $prod['slug']);
        $this->assertSame(149.99, $prod['price']);
        $this->assertSame(20, $prod['stock_qty']);
        $this->assertFalse($prod['vat_exempt']);
        $this->assertTrue($prod['active']);
    }

    public function test_admin_create_auto_increments_duplicate_slug(): void
    {
        $this->seedProduct();
        $result = $this->withAdmin()->post('admin/shop/products', ['name' => 'Test Product', 'price' => 10]);
        $result->assertStatus(201);
        $this->assertSame('test-product-2', $this->json($result)['product']['slug']);
    }

    // ── Admin: PUT /admin/shop/products/:id ─────────────────────────

    public function test_admin_update_returns_404_for_unknown_product(): void
    {
        $this->withAdmin()->put('admin/shop/products/9999', ['name' => 'X'])->assertStatus(404);
    }

    public function test_admin_update_partial_fields(): void
    {
        $prod   = $this->seedProduct();
        $result = $this->withAdmin()->put("admin/shop/products/{$prod['id']}", [
            'price'      => 199.00,
            'vat_exempt' => true,
        ]);
        $result->assertStatus(200);

        $updated = $this->json($result)['product'];
        $this->assertSame(199.0, (float) $updated['price']);
        $this->assertTrue($updated['vat_exempt']);
        $this->assertSame('Test Product', $updated['name']); // unchanged
    }

    public function test_admin_update_renames_slug_when_name_changes(): void
    {
        $prod   = $this->seedProduct();
        $result = $this->withAdmin()->put("admin/shop/products/{$prod['id']}", ['name' => 'Renamed Product']);
        $this->assertSame('renamed-product', $this->json($result)['product']['slug']);
    }

    // ── Admin: DELETE /admin/shop/products/:id ──────────────────────

    public function test_admin_delete_returns_404_for_unknown_product(): void
    {
        $this->withAdmin()->delete('admin/shop/products/9999')->assertStatus(404);
    }

    public function test_admin_delete_removes_product_and_cascades(): void
    {
        $prod = $this->seedProduct();
        $db   = \Config\Database::connect($this->DBGroup);
        $db->table('shop_product_images')->insert([
            'product_id' => $prod['id'], 'url' => 'https://cdn.example.com/img.jpg', 'position' => 0,
        ]);

        $this->withAdmin()->delete("admin/shop/products/{$prod['id']}")->assertStatus(200);

        $this->assertSame(0, $db->table('shop_products')->where('id', $prod['id'])->countAllResults());
        $this->assertSame(0, $db->table('shop_product_images')->where('product_id', $prod['id'])->countAllResults());
    }
}
