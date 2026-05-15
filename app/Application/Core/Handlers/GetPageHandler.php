<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Queries\GetPageQuery;
use App\Domain\Core\Page;
use App\Domain\Core\PageRepositoryInterface;

final class GetPageHandler
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function handle(GetPageQuery $query): ?Page
    {
        return $this->pages->findBySlug($query->slug);
    }
}
