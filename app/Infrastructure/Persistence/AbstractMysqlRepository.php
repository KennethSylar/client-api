<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\PaginatedResult;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseBuilder;

abstract class AbstractMysqlRepository
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    protected function paginate(BaseBuilder $builder, int $page, int $perPage): PaginatedResult
    {
        $total = $builder->countAllResults(reset: false);
        $items = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return new PaginatedResult(
            items:   $items,
            total:   $total,
            page:    $page,
            perPage: $perPage,
        );
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    protected function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    protected function uniqueSlug(string $table, string $base, ?int $excludeId = null): string
    {
        $slug   = $base;
        $suffix = 2;

        while (true) {
            $q = $this->db->table($table)->where('slug', $slug);
            if ($excludeId !== null) {
                $q->where('id !=', $excludeId);
            }
            if ($q->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $suffix++;
        }
    }
}
