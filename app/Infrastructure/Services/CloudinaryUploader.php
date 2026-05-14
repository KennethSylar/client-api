<?php

namespace App\Infrastructure\Services;

use App\Application\Ports\ImageUploaderInterface;
use Cloudinary\Cloudinary;

class CloudinaryUploader implements ImageUploaderInterface
{
    public function uploadImage(string $tempPath, string $folder = 'images'): string
    {
        $cloudinary = new Cloudinary(getenv('CLOUDINARY_URL'));

        $result = $cloudinary->uploadApi()->upload($tempPath, [
            'folder'        => 'jnv/' . $folder,
            'resource_type' => 'image',
        ]);

        return $result['secure_url'];
    }

    public function uploadPdf(string $tempPath, string $folder = 'pdfs'): string
    {
        $cloudinary = new Cloudinary(getenv('CLOUDINARY_URL'));
        $filename   = pathinfo($tempPath, PATHINFO_FILENAME);

        $result = $cloudinary->uploadApi()->upload($tempPath, [
            'folder'          => 'jnv/' . $folder,
            'public_id'       => $filename,
            'resource_type'   => 'raw',
            'use_filename'    => true,
            'unique_filename' => true,
        ]);

        return $result['secure_url'];
    }
}
