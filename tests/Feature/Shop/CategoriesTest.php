<?php

namespace Tests\Feature\Shop;

use Tests\Support\FeatureTestCase;

/**
 * Tests for:
 *   GET  /shop/categories
 *   POST /admin/shop/categories
 *   PUT  /admin/shop/categories/:id
 *   DEL  /admin/shop/categories/:id
 *   PATCH /admin/shop/categories/reorder
 */
class CategoriesTest extends FeatureTestCase
{
    // ── Public: GET /shop/categories ────────────────────────────────

    public function test_public_list_returns_503_when_shop_disabled(): void
    {
        $result = $this->get('shop/categories');
        $result->assertStatus(503);
    }

    public function test_public_list_returns_empty_when_no_categories(): void
    {
        $this->enableShop();
        $result = $this->get('shop/categories');
        $result->assertStatus(200);
        $this->assertSame([], $this->json($result)['categories']);
    }

    public function test_public_list_returns_categories_with_product_count(): void
    {
        $this->enableShop();
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'tools', 'name' => 'Tools', 'position' => 0]);

        $result = $this->get('shop/categories');
        $result->assertStatus(200);

        $cats = $this->json($result)['categories'];
        $this->assertCount(1, $cats);
        $this->assertSame('Tools', $cats[0]['name']);
        $this->assertSame('tools', $cats[0]['slug']);
        $this->assertSame(0, $cats[0]['product_count']);
    }

    public function test_public_list_counts_only_active_products(): void
    {
        $this->enableShop();
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'gear', 'name' => 'Gear', 'position' => 0]);
        $catId = $db->insertID();

        $db->table('shop_products')->insert(['slug' => 'widget', 'name' => 'Widget', 'category_id' => $catId, 'price' => 100, 'active' => 1]);
        $db->table('shop_products')->insert(['slug' => 'hidden', 'name' => 'Hidden', 'category_id' => $catId, 'price' => 50,  'active' => 0]);

        $cats = $this->json($this->get('shop/categories'))['categories'];
        $this->assertSame(1, $cats[0]['product_count']);
    }

    // ── Admin: POST /admin/shop/categories ──────────────────────────

    public function test_create_requires_auth(): void
    {
        $result = $this->post('admin/shop/categories', ['name' => 'Widgets']);
        $result->assertStatus(401);
    }

    public function test_create_validates_name(): void
    {
        $result = $this->withAdmin()->post('admin/shop/categories', ['name' => '']);
        $result->assertStatus(400);
    }

    public function test_create_returns_201_with_category(): void
    {
        $result = $this->withAdmin()->post('admin/shop/categories', ['name' => 'Power Tools']);
        $result->assertStatus(201);

        $cat = $this->json($result)['category'];
        $this->assertSame('Power Tools', $cat['name']);
        $this->assertSame('power-tools', $cat['slug']);
        $this->assertNull($cat['parent_id']);
    }

    public function test_create_auto_increments_duplicate_slug(): void
    {
        $this->withAdmin()->post('admin/shop/categories', ['name' => 'Tools']);
        $result = $this->withAdmin()->post('admin/shop/categories', ['name' => 'Tools']);
        $result->assertStatus(201);
        $this->assertSame('tools-2', $this->json($result)['category']['slug']);
    }

    public function test_create_accepts_parent_id(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'parent', 'name' => 'Parent', 'position' => 0]);
        $parentId = (int) $db->insertID();

        $result = $this->withAdmin()->post('admin/shop/categories', [
            'name'      => 'Child',
            'parent_id' => $parentId,
        ]);
        $result->assertStatus(201);
        $this->assertSame($parentId, $this->json($result)['category']['parent_id']);
    }

    // ── Admin: PUT /admin/shop/categories/:id ───────────────────────

    public function test_update_returns_404_for_missing_category(): void
    {
        $result = $this->withAdmin()->put('admin/shop/categories/9999', ['name' => 'X']);
        $result->assertStatus(404);
    }

    public function test_update_changes_name_and_renames_slug(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'old-name', 'name' => 'Old Name', 'position' => 0]);
        $id = (int) $db->insertID();

        $result = $this->withAdmin()->put("admin/shop/categories/{$id}", ['name' => 'New Name']);
        $result->assertStatus(200);

        $cat = $this->json($result)['category'];
        $this->assertSame('New Name', $cat['name']);
        $this->assertSame('new-name', $cat['slug']);
    }

    public function test_update_rejects_self_parent(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'cat', 'name' => 'Cat', 'position' => 0]);
        $id = (int) $db->insertID();

        $result = $this->withAdmin()->put("admin/shop/categories/{$id}", ['parent_id' => $id]);
        $result->assertStatus(400);
    }

    // ── Admin: DELETE /admin/shop/categories/:id ────────────────────

    public function test_delete_returns_404_for_missing_category(): void
    {
        $result = $this->withAdmin()->delete('admin/shop/categories/9999');
        $result->assertStatus(404);
    }

    public function test_delete_reassigns_children_to_deleted_parent(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'parent', 'name' => 'Parent', 'position' => 0]);
        $parentId = (int) $db->insertID();
        $db->table('shop_categories')->insert(['slug' => 'child', 'name' => 'Child', 'position' => 0, 'parent_id' => $parentId]);
        $childId = (int) $db->insertID();

        $this->withAdmin()->delete("admin/shop/categories/{$parentId}")->assertStatus(200);

        $child = $db->table('shop_categories')->where('id', $childId)->get()->getRowArray();
        $this->assertNull($child['parent_id']);
    }

    public function test_delete_unlinks_products(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'cat', 'name' => 'Cat', 'position' => 0]);
        $catId = (int) $db->insertID();
        $db->table('shop_products')->insert(['slug' => 'prod', 'name' => 'Prod', 'category_id' => $catId, 'price' => 10]);
        $prodId = (int) $db->insertID();

        $this->withAdmin()->delete("admin/shop/categories/{$catId}")->assertStatus(200);

        $prod = $db->table('shop_products')->where('id', $prodId)->get()->getRowArray();
        $this->assertNull($prod['category_id']);
    }

    // ── Admin: PATCH /admin/shop/categories/reorder ─────────────────

    public function test_reorder_updates_positions(): void
    {
        $db = \Config\Database::connect($this->DBGroup);
        $db->table('shop_categories')->insert(['slug' => 'a', 'name' => 'A', 'position' => 0]);
        $idA = (int) $db->insertID();
        $db->table('shop_categories')->insert(['slug' => 'b', 'name' => 'B', 'position' => 1]);
        $idB = (int) $db->insertID();

        $result = $this->withAdmin()->patch('admin/shop/categories/reorder', [
            'order' => [
                ['id' => $idA, 'position' => 1],
                ['id' => $idB, 'position' => 0],
            ],
        ]);
        $result->assertStatus(200);

        $a = $db->table('shop_categories')->where('id', $idA)->get()->getRowArray();
        $b = $db->table('shop_categories')->where('id', $idB)->get()->getRowArray();
        $this->assertSame(1, (int) $a['position']);
        $this->assertSame(0, (int) $b['position']);
    }
}
