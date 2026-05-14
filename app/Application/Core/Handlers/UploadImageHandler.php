<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\UploadImageCommand;
use App\Application\Ports\ImageUploaderInterface;

final class UploadImageHandler
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_BYTES    = 5 * 1024 * 1024; // 5 MB

    public function __construct(
        private readonly ImageUploaderInterface $uploader,
    ) {}

    /** Returns the secure CDN URL. */
    public function handle(UploadImageCommand $cmd): string
    {
        if (!in_array($cmd->mimeType, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException('Only JPEG, PNG, WebP and GIF images are allowed.');
        }

        if ($cmd->sizeBytes > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File must be under 5 MB.');
        }

        return $this->uploader->uploadImage($cmd->tempPath, $cmd->folder);
    }
}
