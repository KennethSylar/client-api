<?php

namespace App\Application\Ports;

interface ImageUploaderInterface
{
    /** Returns the secure CDN URL of the uploaded image. */
    public function uploadImage(string $tempPath, string $folder = 'images'): string;

    /** Returns the secure CDN URL of the uploaded PDF. */
    public function uploadPdf(string $tempPath, string $folder = 'pdfs'): string;
}
