<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Core\AdminUserRepositoryInterface;

class MySqlAdminUserRepository extends AbstractMysqlRepository implements AdminUserRepositoryInterface
{
    public function findByEmail(string $email): ?array
    {
        $row = $this->db->table('admin_users')
            ->where('email', $email)
            ->get()->getRowArray();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->table('admin_users')
            ->select('id, name, email, role, is_active, created_at')
            ->where('id', $id)
            ->get()->getRowArray();

        return $row ?: null;
    }

    public function list(): array
    {
        return $this->db->table('admin_users')
            ->select('id, name, email, role, is_active, created_at')
            ->orderBy('created_at', 'ASC')
            ->get()->getResultArray();
    }

    public function create(string $name, string $email, string $passwordHash, string $role): int
    {
        $now = $this->now();
        $this->db->table('admin_users')->insert([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'role'          => $role,
            'is_active'     => 1,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return (int) $this->db->insertID();
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = $this->now();
        $this->db->table('admin_users')->where('id', $id)->update($data);
    }

    public function delete(int $id): void
    {
        $this->db->table('admin_users')->where('id', $id)->delete();
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->db->table('admin_users')
            ->where('role', 'admin')
            ->where('is_active', 1)
            ->countAllResults();
    }
}
