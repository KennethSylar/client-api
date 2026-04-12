<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Upload extends BaseController
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_BYTES     = 5 * 1024 * 1024; // 5 MB

    public function store(): \CodeIgniter\HTTP\ResponseInterface
    {
        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $this->error('No valid file provided.', 422);
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME)) {
            return $this->error('Only JPEG, PNG, WebP and GIF images are allowed.', 422);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return $this->error('File must be under 5 MB.', 422);
        }

        $uploadDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = $file->getRandomName();
        $file->move($uploadDir, $filename);

        return $this->json(['url' => '/uploads/' . $filename]);
    }
}
