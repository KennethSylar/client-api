<?php

namespace App\Application\Core\Commands;

final class UploadPdfCommand
{
    public function __construct(
        public readonly string $tempPath,
        public readonly int    $sizeBytes,
        public readonly string $folder = 'pdfs',
    ) {}
}
