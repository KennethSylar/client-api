<?php

namespace App\Application\Core\Handlers;

use App\Application\Core\Commands\SavePageCommand;
use App\Domain\Core\Page;
use App\Domain\Core\PageRepositoryInterface;

final class SavePageHandler
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function handle(SavePageCommand $cmd): void
    {
        if ($cmd->mustBeNew && $this->pages->findBySlug($cmd->slug) !== null) {
            throw new \DomainException("A page with slug '{$cmd->slug}' already exists.");
        }

        $data = [
            'eyebrow'        => $cmd->eyebrow,
            'title'          => $cmd->title,
            'body'           => $cmd->body,
            'image'          => $cmd->image,
            'seoTitle'       => $cmd->seoTitle,
            'seoDescription' => $cmd->seoDescription,
            'content'        => $cmd->content ?: ['html' => ''],
        ];

        $page = new Page(
            slug:      $cmd->slug,
            data:      $data,
            updatedAt: new \DateTimeImmutable(),
        );

        $this->pages->save($page);
    }
}
