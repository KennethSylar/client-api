<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Orders\CustomerAddress;
use App\Domain\Orders\CustomerAddressRepositoryInterface;
use CodeIgniter\Database\ConnectionInterface;

class MySqlCustomerAddressRepository implements CustomerAddressRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function findByCustomer(int $customerId): array
    {
        $rows = $this->db->table('shop_customer_addresses')
            ->where('customer_id', $customerId)
            ->orderBy('is_default', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->get()->getResultArray();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $id, int $customerId): ?CustomerAddress
    {
        $row = $this->db->table('shop_customer_addresses')
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->get()->getRowArray();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(CustomerAddress $address): CustomerAddress
    {
        $data = [
            'customer_id'   => $address->customerId,
            'label'         => $address->label,
            'first_name'    => $address->firstName,
            'last_name'     => $address->lastName,
            'phone'         => $address->phone,
            'address_line1' => $address->addressLine1,
            'address_line2' => $address->addressLine2,
            'city'          => $address->city,
            'province'      => $address->province,
            'postal_code'   => $address->postalCode,
            'country'       => $address->country,
            'is_default'    => $address->isDefault ? 1 : 0,
        ];

        if ($address->id === 0) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('shop_customer_addresses')->insert($data);
            $id = (int) $this->db->insertID();
        } else {
            $this->db->table('shop_customer_addresses')
                ->where('id', $address->id)
                ->where('customer_id', $address->customerId)
                ->update($data);
            $id = $address->id;
        }

        return $this->findById($id, $address->customerId);
    }

    public function delete(int $id, int $customerId): void
    {
        $this->db->table('shop_customer_addresses')
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->delete();
    }

    public function setDefault(int $id, int $customerId): void
    {
        $this->db->table('shop_customer_addresses')
            ->where('customer_id', $customerId)
            ->update(['is_default' => 0]);

        $this->db->table('shop_customer_addresses')
            ->where('id', $id)
            ->where('customer_id', $customerId)
            ->update(['is_default' => 1]);
    }

    private function hydrate(array $row): CustomerAddress
    {
        return new CustomerAddress(
            id:           (int) $row['id'],
            customerId:   (int) $row['customer_id'],
            label:        $row['label'] ?? null,
            firstName:    $row['first_name'],
            lastName:     $row['last_name'],
            phone:        $row['phone'] ?? null,
            addressLine1: $row['address_line1'],
            addressLine2: $row['address_line2'] ?? null,
            city:         $row['city'],
            province:     $row['province'] ?? null,
            postalCode:   $row['postal_code'],
            country:      $row['country'],
            isDefault:    (bool) $row['is_default'],
            createdAt:    new \DateTimeImmutable($row['created_at']),
        );
    }
}
