<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Infrastructure\Http\Controllers\BaseController;

class Categories extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $categories = service('categoryRepository')->findAllWithProductCount();

        return $this->ok(['categories' => $categories]);
    }
}
