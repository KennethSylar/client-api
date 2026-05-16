<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Core\AdminSessionRepositoryInterface;

class MySqlAdminSessionRepository extends AbstractMysqlRepository implements AdminSessionRepositoryInterface
{
    public function create(string $token, string $expiresAt, int $userId, string $role): void
    {
        $this->db->table('admin_sessions')->insert([
            'token'      => $token,
            'expires_at' => $expiresAt,
            'user_id'    => $userId,
            'role'       => $role,
        ]);
    }

    public function find(string $token): ?array
    {
        $row = $this->db->table('admin_sessions')
            ->where('token', $token)
            ->where('expires_at >', $this->now())
            ->get()->getRowArray();

        return $row ?: null;
    }

    public function delete(string $token): void
    {
        $this->db->table('admin_sessions')->where('token', $token)->delete();
    }

    public function deleteExpired(): void
    {
        $this->db->table('admin_sessions')->where('expires_at <', $this->now())->delete();
    }
}
