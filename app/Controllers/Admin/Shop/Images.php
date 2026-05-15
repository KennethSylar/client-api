<?php

namespace App\Controllers\Admin\Shop;

use App\Application\Shop\Commands\AddProductImageCommand;
use App\Application\Shop\Commands\DeleteProductImageCommand;
use App\Application\Shop\Commands\ReorderProductImagesCommand;
use App\Controllers\BaseController;

class Images extends BaseController
{
    public function store(int $productId): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        $url  = trim($body['url'] ?? '');

        if ($url === '') {
            return $this->error('url is required.', 400);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('url must be a valid URL.', 400);
        }

        try {
            $image = service('addProductImageHandler')->handle(new AddProductImageCommand(
                productId: $productId,
                url:       $url,
                alt:       trim($body['alt'] ?? ''),
                position:  isset($body['position']) ? (int) $body['position'] : null,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->json(['image' => $image->toArray()], 201);
    }

    public function delete(int $productId, int $imageId): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            service('deleteProductImageHandler')->handle(new DeleteProductImageCommand($productId, $imageId));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->ok();
    }

    public function reorder(int $productId): \CodeIgniter\HTTP\ResponseInterface
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

        try {
            $images = service('reorderProductImagesHandler')->handle(new ReorderProductImagesCommand(
                productId: $productId,
                positions: $positions,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->ok([
            'images' => array_map(fn($i) => [
                'id'       => $i->id,
                'url'      => $i->url,
                'alt'      => $i->alt,
                'position' => $i->position,
            ], $images),
        ]);
    }
}
