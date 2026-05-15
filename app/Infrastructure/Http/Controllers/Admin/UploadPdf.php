<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Core\Commands\UploadPdfCommand;
use App\Infrastructure\Http\Controllers\BaseController;

class UploadPdf extends BaseController
{
    public function store(): \CodeIgniter\HTTP\ResponseInterface
    {
        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $this->error('No valid file provided.', 422);
        }

        $mime = $file->getMimeType();
        $name = strtolower($file->getClientName());

        if ($mime !== 'application/pdf' && !str_ends_with($name, '.pdf')) {
            return $this->error('Only PDF files are accepted.', 422);
        }

        try {
            $url = service('uploadPdfHandler')->handle(new UploadPdfCommand(
                tempPath:  $file->getTempName(),
                sizeBytes: $file->getSize(),
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            log_message('error', 'Cloudinary PDF upload failed: ' . $e->getMessage());
            return $this->error('Upload failed. Please try again.', 500);
        }

        return $this->json([
            'url'      => $url,
            'filename' => $file->getClientName(),
            'size'     => $this->formatBytes($file->getSize()),
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        return 'PDF · ~' . round($bytes / 1024 / 1024, 1) . ' MB';
    }
}
