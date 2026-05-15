<?php

namespace App\Infrastructure\Http\Controllers\Content;

use App\Application\Core\Queries\GetPageQuery;
use App\Application\Core\Queries\ListPagesQuery;
use App\Infrastructure\Http\Controllers\BaseController;

class Pages extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $pages = service('listPagesHandler')->handle(new ListPagesQuery());

        return $this->json(array_map(fn($p) => [
            'slug'       => $p->slug,
            'title'      => $p->data['title'] ?? $p->slug,
            'updated_at' => $p->updatedAt->format('Y-m-d H:i:s'),
        ], $pages));
    }

    public function show(string $slug): \CodeIgniter\HTTP\ResponseInterface
    {
        $page = service('getPageHandler')->handle(new GetPageQuery($slug));

        if ($page === null) {
            return $this->notFound("Page '{$slug}' not found.");
        }

        $data = $page->data;
        if (!isset($data['content']) || !is_array($data['content'])) {
            $data['content'] = (object) [];
        }

        return $this->json($data);
    }
}
