<?php

namespace App\Controllers\Admin\Shop;

use App\Application\Shop\Commands\CreateCategoryCommand;
use App\Application\Shop\Commands\DeleteCategoryCommand;
use App\Application\Shop\Commands\ReorderCategoriesCommand;
use App\Application\Shop\Commands\UpdateCategoryCommand;
use App\Controllers\BaseController;

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
