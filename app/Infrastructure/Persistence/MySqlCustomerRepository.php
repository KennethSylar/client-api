<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Orders\Customer;
use App\Domain\Orders\CustomerRepositoryInterface;

class MySqlCustomerRepository extends AbstractMysqlRepository implements CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer
    {
        $row = $this->db->table('shop_customers')->where('id', $id)->get()->getRowArray();
        return $row ? Customer::fromArray($row) : null;
    }

    public function findByEmail(string $email): ?Customer
    {
        $row = $this->db->table('shop_customers')
            ->where('email', strtolower(trim($email)))
            ->get()->getRowArray();

        return $row ? Customer::fromArray($row) : null;
    }

    public function findByToken(string $token): ?Customer
    {
        $session = $this->db->table('shop_customer_sessions')
            ->where('token', $token)
            ->where('expires_at >', $this->now())
            ->get()->getRowArray();

        if (!$session) return null;

        return $this->findById((int) $session['customer_id']);
    }

    public function save(Customer $customer, ?string $passwordHash = null): Customer
    {
        $payload = [
            'email'          => strtolower(trim($customer->email)),
            'first_name'     => $customer->firstName,
            'last_name'      => $customer->lastName,
            'phone'          => $customer->phone,
            'email_verified' => (int) $customer->emailVerified,
            'updated_at'     => $this->now(),
        ];

        if ($passwordHash !== null) {
            $payload['password_hash'] = $passwordHash;
        }

        if ($customer->id === 0) {
            $payload['created_at'] = $this->now();
            $this->db->table('shop_customers')->insert($payload);
            $id = (int) $this->db->insertID();
        } else {
            $this->db->table('shop_customers')->where('id', $customer->id)->update($payload);
            $id = $customer->id;
        }

        return $this->findById($id);
    }

    public function getPasswordHash(int $customerId): ?string
    {
        $row = $this->db->table('shop_customers')
            ->select('password_hash')
            ->where('id', $customerId)
            ->get()->getRowArray();

        return $row ? ($row['password_hash'] ?? null) : null;
    }

    public function createSession(int $customerId, string $token, \DateTimeImmutable $expiresAt): void
    {
        $this->db->table('shop_customer_sessions')->insert([
            'customer_id' => $customerId,
            'token'       => $token,
            'expires_at'  => $expiresAt->format('Y-m-d H:i:s'),
            'created_at'  => $this->now(),
        ]);
    }

    public function findSession(string $token): ?array
    {
        $row = $this->db->table('shop_customer_sessions')
            ->where('token', $token)
            ->where('expires_at >', $this->now())
            ->get()->getRowArray();

        return $row ?: null;
    }

    public function deleteSession(string $token): void
    {
        $this->db->table('shop_customer_sessions')->where('token', $token)->delete();
    }

    public function linkGuestOrders(int $customerId, string $email): void
    {
        $this->db->table('shop_orders')
            ->where('email', strtolower(trim($email)))
            ->where('customer_id IS NULL', null, false)
            ->update(['customer_id' => $customerId]);
    }
}
