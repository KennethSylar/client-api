<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Shop\Category;
use App\Domain\Shop\CategoryRepositoryInterface;

class MySqlCategoryRepository extends AbstractMysqlRepository implements CategoryRepositoryInterface
{
    public function findAll(): array
    {
        $rows = $this->db->table('shop_categories')
            ->orderBy('position', 'ASC')
            ->get()->getResultArray();

        return array_map(fn($r) => Category::fromArray($r), $rows);
    }

    public function findAllWithProductCount(): array
    {
        $rows = $this->db->query("
            SELECT c.id, c.slug, c.name, c.parent_id, c.position,
                   COUNT(p.id) AS product_count
            FROM shop_categories c
            LEFT JOIN shop_products p ON p.category_id = c.id AND p.active = 1
            GROUP BY c.id
            ORDER BY c.position ASC, c.name ASC
        ")->getResultArray();

        foreach ($rows as &$row) {
            $row['id']            = (int) $row['id'];
            $row['parent_id']     = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $row['position']      = (int) $row['position'];
            $row['product_count'] = (int) $row['product_count'];
        }

        return $rows;
    }

    public function findById(int $id): ?Category
    {
        $row = $this->db->table('shop_categories')->where('id', $id)->get()->getRowArray();
        return $row ? Category::fromArray($row) : null;
    }

    public function save(Category $category): Category
    {
        if ($category->id === 0) {
            // Insert
            $slug = $this->uniqueSlug('shop_categories', $this->slugify($category->name));
            $this->db->table('shop_categories')->insert([
                'name'      => $category->name,
                'slug'      => $slug,
                'parent_id' => $category->parentId,
                'position'  => $category->position,
            ]);
            $id = (int) $this->db->insertID();
        } else {
            // Update
            $this->db->table('shop_categories')->where('id', $category->id)->update([
                'name'      => $category->name,
                'slug'      => $category->slug,
                'parent_id' => $category->parentId,
                'position'  => $category->position,
            ]);
            $id = $category->id;
        }

        $row = $this->db->table('shop_categories')->where('id', $id)->get()->getRowArray();
        return Category::fromArray($row);
    }

    public function delete(int $id): void
    {
        $category = $this->db->table('shop_categories')->where('id', $id)->get()->getRowArray();
        if (!$category) return;

        // Reassign child categories to this category's parent (or null)
        $this->db->table('shop_categories')
            ->where('parent_id', $id)
            ->update(['parent_id' => $category['parent_id']]);

        // Unlink products (set category_id = null, not delete)
        $this->db->table('shop_products')
            ->where('category_id', $id)
            ->update(['category_id' => null]);

        $this->db->table('shop_categories')->where('id', $id)->delete();
    }

    public function reorder(array $positions): void
    {
        foreach ($positions as $id => $position) {
            $this->db->table('shop_categories')
                ->where('id', (int) $id)
                ->update(['position' => (int) $position]);
        }
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $q = $this->db->table('shop_categories')->where('slug', $slug);
        if ($excludeId !== null) {
            $q->where('id !=', $excludeId);
        }
        return $q->countAllResults() > 0;
    }
}
