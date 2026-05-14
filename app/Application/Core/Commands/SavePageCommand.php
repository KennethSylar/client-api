<?php

namespace App\Application\Core\Commands;

final class SavePageCommand
{
    public function __construct(
        public readonly string  $slug,
        public readonly string  $eyebrow,
        public readonly string  $title,
        public readonly string  $body,
        public readonly string  $image,
        public readonly string  $seoTitle,
        public readonly string  $seoDescription,
        public readonly array   $content,
        /** true = INSERT (must not already exist), false = UPSERT */
        public readonly bool    $mustBeNew = false,
    ) {}
}
