<?php

namespace App\Application\Shop\Commands;

final class AddProductImageCommand
{
    public function __construct(
        public readonly int     $productId,
        public readonly string  $url,
        public readonly string  $alt      = '',
        public readonly ?int    $position = null,
    ) {}
}
