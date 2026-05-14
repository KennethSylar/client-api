<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Core\SettingsRepositoryInterface;

class MySqlSettingsRepository extends AbstractMysqlRepository implements SettingsRepositoryInterface
{
    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->table('settings')->where('key', $key)->get()->getRowArray();
        return $row ? (string) $row['value'] : $default;
    }

    public function getMany(array $keys): array
    {
        if (empty($keys)) return [];

        $rows = $this->db->table('settings')
            ->whereIn('key', $keys)
            ->get()->getResultArray();

        return array_column($rows, 'value', 'key');
    }

    public function set(string $key, string $value): void
    {
        $exists = $this->db->table('settings')->where('key', $key)->countAllResults() > 0;

        if ($exists) {
            $this->db->table('settings')->where('key', $key)->update(['value' => $value]);
        } else {
            $this->db->table('settings')->insert(['key' => $key, 'value' => $value]);
        }
    }

    public function setMany(array $keyValues): void
    {
        foreach ($keyValues as $key => $value) {
            $this->set($key, $value);
        }
    }
}
