<?php

namespace App\Controllers\Admin;

use App\Application\Core\Commands\UploadImageCommand;
use App\Controllers\BaseController;

class Upload extends BaseController
{
    public function store(): \CodeIgniter\HTTP\ResponseInterface
    {
        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $this->error('No valid file provided.', 422);
        }

        try {
            $url = service('uploadImageHandler')->handle(new UploadImageCommand(
                tempPath:  $file->getTempName(),
                mimeType:  $file->getMimeType(),
                sizeBytes: $file->getSize(),
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            log_message('error', 'Cloudinary image upload failed: ' . $e->getMessage());
            return $this->error('Upload failed. Please try again.', 500);
        }

        return $this->json(['url' => $url]);
    }
}
