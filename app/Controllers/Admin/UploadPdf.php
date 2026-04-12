<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Admin\UploadPdf
 *
 * Accepts a PDF file upload and saves it to public/uploads/pdfs/.
 * Called by the Nuxt AdminPdfDropzone when Cloudinary is not configured.
 * Route: POST /admin/upload-pdf  (adminauth filter)
 */
class UploadPdf extends BaseController
{
    private const MAX_BYTES = 20 * 1024 * 1024; // 20 MB

    public function store(): \CodeIgniter\HTTP\ResponseInterface
    {
        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $this->error('No valid file provided.', 422);
        }

        // Validate PDF
        $mime = $file->getMimeType();
        $name = strtolower($file->getClientName());

        if ($mime !== 'application/pdf' && !str_ends_with($name, '.pdf')) {
            return $this->error('Only PDF files are accepted.', 422);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return $this->error('File must be under 20 MB.', 422);
        }

        $uploadDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'pdfs' . DIRECTORY_SEPARATOR;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Use a random name to prevent collisions / path traversal
        $filename = bin2hex(random_bytes(8)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientName());
        $file->move($uploadDir, $filename);

        return $this->json(['url' => '/uploads/pdfs/' . $filename]);
    }
}
