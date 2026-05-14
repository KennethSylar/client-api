<?php

namespace App\Application\Core\Commands;

final class UploadImageCommand
{
    public function __construct(
        public readonly string $tempPath,
        public readonly string $mimeType,
        public readonly int    $sizeBytes,
        public readonly string $folder = 'images',
    ) {}
}
