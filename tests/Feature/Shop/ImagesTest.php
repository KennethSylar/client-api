<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   POST   /admin/shop/products/:id/images
 *   DELETE /admin/shop/products/:id/images/:image_id
 *   PATCH  /admin/shop/products/:id/images/reorder
 */
class ImagesTest extends FeatureTestCase
{
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_products')->insert([
            'slug' => 'test-product', 'name' => 'Test Product', 'price' => 10, 'active' => 1,
        ]);
        $this->productId = (int) $db->insertID();
    }

    private function addImage(string $url = 'https://cdn.example.com/img.jpg', int $position = 0): int
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_product_images')->insert([
            'product_id' => $this->productId, 'url' => $url, 'alt' => '', 'position' => $position,
        ]);
        return (int) $db->insertID();
    }

    // ── POST /admin/shop/products/:id/images ────────────────────────

    public function test_store_requires_auth(): void
    {
        $this->post("admin/shop/products/{$this->productId}/images", ['url' => 'https://cdn.example.com/img.jpg'])
            ->assertStatus(401);
    }

    public function test_store_returns_404_for_unknown_product(): void
    {
        $result = $this->withAdmin()->post('admin/shop/products/9999/images', [
            'url' => 'https://cdn.example.com/img.jpg',
        ]);
        $result->assertStatus(404);
    }

    public function test_store_requires_url(): void
    {
        $this->withAdmin()->post("admin/shop/products/{$this->productId}/images", [])->assertStatus(400);
    }

    public function test_store_validates_url_format(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/images", [
            'url' => 'not-a-url',
        ]);
        $result->assertStatus(400);
    }

    public function test_store_saves_image_and_returns_201(): void
    {
        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/images", [
            'url' => 'https://res.cloudinary.com/demo/image/upload/sample.jpg',
            'alt' => 'Product front view',
        ]);
        $result->assertStatus(201);

        $image = $this->json($result)['image'];
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/sample.jpg', $image['url']);
        $this->assertSame('Product front view', $image['alt']);
        $this->assertSame(0, $image['position']); // first image defaults to position 0
    }

    public function test_store_auto_increments_position(): void
    {
        $this->addImage('https://cdn.example.com/first.jpg', 0);

        $result = $this->withAdmin()->post("admin/shop/products/{$this->productId}/images", [
            'url' => 'https://cdn.example.com/second.jpg',
        ]);
        $result->assertStatus(201);
        $this->assertSame(1, $this->json($result)['image']['position']);
    }

    // ── DELETE /admin/shop/products/:id/images/:image_id ────────────

    public function test_delete_requires_auth(): void
    {
        $imageId = $this->addImage();
        $this->delete("admin/shop/products/{$this->productId}/images/{$imageId}")->assertStatus(401);
    }

    public function test_delete_returns_404_for_unknown_image(): void
    {
        $this->withAdmin()->delete("admin/shop/products/{$this->productId}/images/9999")->assertStatus(404);
    }

    public function test_delete_removes_image(): void
    {
        $imageId = $this->addImage();
        $this->withAdmin()->delete("admin/shop/products/{$this->productId}/images/{$imageId}")->assertStatus(200);

        $db = \Config\Database::connect($this->DBGroup);
        $this->assertSame(0, $db->table('shop_product_images')->where('id', $imageId)->countAllResults());
    }

    public function test_delete_repacks_positions(): void
    {
        $id0 = $this->addImage('https://cdn.example.com/0.jpg', 0);
        $id1 = $this->addImage('https://cdn.example.com/1.jpg', 1);
        $id2 = $this->addImage('https://cdn.example.com/2.jpg', 2);

        // Delete the middle image
        $this->withAdmin()->delete("admin/shop/products/{$this->productId}/images/{$id1}");

        $db   = \Config\Database::connect($this->DBGroup);
        $pos0 = $db->table('shop_product_images')->where('id', $id0)->get()->getRowArray()['position'];
        $pos2 = $db->table('shop_product_images')->where('id', $id2)->get()->getRowArray()['position'];

        $this->assertSame(0, (int) $pos0); // unchanged
        $this->assertSame(1, (int) $pos2); // repacked from 2 → 1
    }

    public function test_delete_cannot_remove_image_from_other_product(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_products')->insert(['slug' => 'other', 'name' => 'Other', 'price' => 10, 'active' => 1]);
        $otherId = (int) $db->insertID();
        $db->table('shop_product_images')->insert(['product_id' => $otherId, 'url' => 'https://cdn.example.com/x.jpg', 'position' => 0]);
        $otherImageId = (int) $db->insertID();

        // Try to delete other product's image via this product's URL
        $this->withAdmin()->delete("admin/shop/products/{$this->productId}/images/{$otherImageId}")->assertStatus(404);
    }

    // ── PATCH /admin/shop/products/:id/images/reorder ───────────────

    public function test_reorder_updates_positions(): void
    {
        $idA = $this->addImage('https://cdn.example.com/a.jpg', 0);
        $idB = $this->addImage('https://cdn.example.com/b.jpg', 1);
        $idC = $this->addImage('https://cdn.example.com/c.jpg', 2);

        $result = $this->withAdmin()->patch("admin/shop/products/{$this->productId}/images/reorder", [
            'order' => [
                ['id' => $idC, 'position' => 0],
                ['id' => $idA, 'position' => 1],
                ['id' => $idB, 'position' => 2],
            ],
        ]);
        $result->assertStatus(200);

        $images = $this->json($result)['images'];
        $this->assertSame($idC, $images[0]['id']); // C is now first
        $this->assertSame($idA, $images[1]['id']);
        $this->assertSame($idB, $images[2]['id']);
    }

    public function test_reorder_requires_order_array(): void
    {
        $this->withAdmin()->patch("admin/shop/products/{$this->productId}/images/reorder", [])->assertStatus(400);
    }
}
