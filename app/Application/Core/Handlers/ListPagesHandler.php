<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Queries\ListPagesQuery;
use App\Domain\Core\Page;
use App\Domain\Core\PageRepositoryInterface;

final class ListPagesHandler
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    /** @return Page[] */
    public function handle(ListPagesQuery $query): array
    {
        return $this->pages->findAll();
    }
}
