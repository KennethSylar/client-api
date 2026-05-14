<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\UploadPdfCommand;
use App\Application\Ports\ImageUploaderInterface;

final class UploadPdfHandler
{
    private const MAX_BYTES = 20 * 1024 * 1024; // 20 MB

    public function __construct(
        private readonly ImageUploaderInterface $uploader,
    ) {}

    /** Returns the secure CDN URL. */
    public function handle(UploadPdfCommand $cmd): string
    {
        if ($cmd->sizeBytes > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File must be under 20 MB.');
        }

        return $this->uploader->uploadPdf($cmd->tempPath, $cmd->folder);
    }
}
