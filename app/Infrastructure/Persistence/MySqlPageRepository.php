<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Core\Page;
use App\Domain\Core\PageRepositoryInterface;

class MySqlPageRepository extends AbstractMysqlRepository implements PageRepositoryInterface
{
    public function findAll(): array
    {
        $rows = $this->db->table('pages')->get()->getResultArray();
        return array_map(fn($r) => Page::fromArray($r), $rows);
    }

    public function findBySlug(string $slug): ?Page
    {
        $row = $this->db->table('pages')->where('slug', $slug)->get()->getRowArray();
        return $row ? Page::fromArray($row) : null;
    }

    public function save(Page $page): void
    {
        $exists = $this->db->table('pages')->where('slug', $page->slug)->countAllResults() > 0;

        $payload = [
            'data'       => json_encode($page->data),
            'updated_at' => $this->now(),
        ];

        if ($exists) {
            $this->db->table('pages')->where('slug', $page->slug)->update($payload);
        } else {
            $this->db->table('pages')->insert(array_merge(['slug' => $page->slug], $payload));
        }
    }

    public function delete(string $slug): void
    {
        $this->db->table('pages')->where('slug', $slug)->delete();
    }
}
