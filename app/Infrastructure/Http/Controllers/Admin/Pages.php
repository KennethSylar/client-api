<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Core\Commands\DeletePageCommand;
use App\Application\Core\Commands\SavePageCommand;
use App\Infrastructure\Http\Controllers\BaseController;

class Pages extends BaseController
{
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();
        $slug = trim($body['slug'] ?? '');

        if (!$slug || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return $this->error('Slug must be lowercase letters, numbers and hyphens (e.g. my-page).', 422);
        }

        try {
            service('savePageHandler')->handle(new SavePageCommand(
                slug:           $slug,
                eyebrow:        $body['eyebrow']        ?? '',
                title:          $body['title']          ?? '',
                body:           $body['body']           ?? '',
                image:          $body['image']          ?? '',
                seoTitle:       $body['seoTitle']       ?? '',
                seoDescription: $body['seoDescription'] ?? '',
                content:        ['html' => ''],
                mustBeNew:      true,
            ));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return $this->json(['slug' => $slug], 201);
    }

    public function delete(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            service('deletePageHandler')->handle(new DeletePageCommand($slug));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 403);
        }

        return $this->ok();
    }

    public function update(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        service('savePageHandler')->handle(new SavePageCommand(
            slug:           $slug,
            eyebrow:        $body['eyebrow']        ?? '',
            title:          $body['title']          ?? '',
            body:           $body['body']           ?? '',
            image:          $body['image']          ?? '',
            seoTitle:       $body['seoTitle']       ?? '',
            seoDescription: $body['seoDescription'] ?? '',
            content:        $body['content']        ?? [],
        ));

        return $this->ok();
    }
}
