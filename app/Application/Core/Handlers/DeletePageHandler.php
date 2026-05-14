<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\DeletePageCommand;
use App\Domain\Core\PageRepositoryInterface;

final class DeletePageHandler
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function handle(DeletePageCommand $cmd): void
    {
        if ($cmd->isProtected()) {
            throw new \DomainException("The '{$cmd->slug}' page cannot be deleted.");
        }

        $this->pages->delete($cmd->slug);
    }
}
