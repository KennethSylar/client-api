<?php

namespace App\Infrastructure\Http\Controllers\Admin\Shop;

use App\Application\Shop\Commands\CreateCategoryCommand;
use App\Application\Shop\Commands\DeleteCategoryCommand;
use App\Application\Shop\Commands\ReorderCategoriesCommand;
use App\Application\Shop\Commands\UpdateCategoryCommand;
use App\Infrastructure\Http\Controllers\BaseController;

class Categories extends BaseController
{
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        $name = trim($body['name'] ?? '');

        if ($name === '') {
            return $this->error('name is required.', 400);
        }

        $category = service('createCategoryHandler')->handle(new CreateCategoryCommand(
            name:     $name,
            parentId: isset($body['parent_id']) ? (int) $body['parent_id'] : null,
            position: isset($body['position'])  ? (int) $body['position']  : 0,
        ));

        return $this->json(['category' => $category->toArray()], 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        try {
            $category = service('updateCategoryHandler')->handle(new UpdateCategoryCommand(
                id:        $id,
                name:      $body['name']     ?? null,
                setParent: array_key_exists('parent_id', $body),
                parentId:  array_key_exists('parent_id', $body) && $body['parent_id'] !== null
                               ? (int) $body['parent_id'] : null,
                position:  isset($body['position']) ? (int) $body['position'] : null,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->ok(['category' => $category->toArray()]);
    }

    public function delete(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            service('deleteCategoryHandler')->handle(new DeleteCategoryCommand($id));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->ok();
    }

    public function export(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db   = \Config\Database::connect();
        $rows = $db->query(
            "SELECT c.id, c.name, c.slug, c.parent_id, c.position,
                    (SELECT name FROM shop_categories WHERE id = c.parent_id) AS parent_name,
                    COUNT(p.id) AS product_count
             FROM shop_categories c
             LEFT JOIN shop_products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.position ASC"
        )->getResultArray();

        if ($this->request->getGet('template') === '1') {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, ['ID', 'Name', 'Slug', 'Parent Name']);
            fputcsv($out, ['', 'Example Category', 'example-category', '']);
            fputcsv($out, ['', 'Example Sub-category', 'example-sub', 'Example Category']);
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);
            return $this->response
                ->setStatusCode(200)
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="categories-import-template.csv"')
                ->setBody("\xEF\xBB\xBF" . $csv);
        }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['ID', 'Name', 'Slug', 'Parent Name', 'Position', 'Product Count']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['name'],
                $r['slug'],
                $r['parent_name'] ?? '',
                $r['position'],
                $r['product_count'],
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="categories-' . date('Y-m-d') . '.csv"')
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

        $db     = \Config\Database::connect();
        $handle = fopen($file->getTempName(), 'r');
        $raw    = fgetcsv($handle);
        $header = array_map(fn($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $raw);

        $created = 0;
        $updated = 0;
        $errors  = [];
        $row     = 1;

        // Build name→id map for resolving parent names
        $existing = $db->query("SELECT id, name FROM shop_categories")->getResultArray();
        $nameToId = array_column($existing, 'id', 'name');

        while (($cols = fgetcsv($handle)) !== false) {
            $row++;
            if (count($cols) < 2) continue;

            $data = array_combine($header, array_pad($cols, count($header), ''));
            $name = trim($data['name'] ?? '');
            if (!$name) {
                $errors[] = "Row {$row}: missing name — skipped.";
                continue;
            }

            $id         = isset($data['id']) && is_numeric($data['id']) ? (int) $data['id'] : null;
            $parentName = trim($data['parent_name'] ?? '');
            $parentId   = $parentName ? ($nameToId[$parentName] ?? null) : null;

            try {
                if ($id) {
                    $check = $db->query("SELECT id FROM shop_categories WHERE id = ?", [$id])->getRowArray();
                    if ($check) {
                        service('updateCategoryHandler')->handle(new UpdateCategoryCommand(
                            id:        $id,
                            name:      $name,
                            setParent: true,
                            parentId:  $parentId,
                        ));
                        $nameToId[$name] = $id;
                        $updated++;
                        continue;
                    }
                }
                $cat = service('createCategoryHandler')->handle(new CreateCategoryCommand(
                    name:     $name,
                    parentId: $parentId,
                ));
                $nameToId[$name] = $cat->id;
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

    public function reorder(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body  = $this->jsonBody();
        $order = $body['order'] ?? [];

        if (!is_array($order) || empty($order)) {
            return $this->error('order array is required.', 400);
        }

        $positions = [];
        foreach ($order as $item) {
            if (!isset($item['id'], $item['position'])) continue;
            $positions[(int) $item['id']] = (int) $item['position'];
        }

        service('reorderCategoriesHandler')->handle(new ReorderCategoriesCommand($positions));

        return $this->ok();
    }
}
