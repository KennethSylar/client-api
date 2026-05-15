<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Domain\Orders\CustomerAddress;
use App\Infrastructure\Http\Controllers\BaseController;

class CustomerAddresses extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $addresses = service('customerAddressRepository')->findByCustomer($customer->id);

        return $this->ok(['addresses' => array_map(fn($a) => $a->toArray(), $addresses)]);
    }

    public function store(): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $body = $this->jsonBody();

        foreach (['first_name', 'last_name', 'address_line1', 'city', 'postal_code'] as $field) {
            if (empty($body[$field])) {
                return $this->error("Missing required field: {$field}", 400);
            }
        }

        $repo    = service('customerAddressRepository');
        $makeDefault = (bool) ($body['is_default'] ?? false);

        // If this will be default, unset current default first
        if ($makeDefault) {
            $existing = $repo->findByCustomer($customer->id);
            if (empty($existing)) $makeDefault = true; // first address always default
        }

        $address = new CustomerAddress(
            id:           0,
            customerId:   $customer->id,
            label:        $body['label'] ?? null,
            firstName:    trim($body['first_name']),
            lastName:     trim($body['last_name']),
            phone:        $body['phone'] ?? null,
            addressLine1: trim($body['address_line1']),
            addressLine2: $body['address_line2'] ?? null,
            city:         trim($body['city']),
            province:     $body['province'] ?? null,
            postalCode:   trim($body['postal_code']),
            country:      strtoupper($body['country'] ?? 'ZA'),
            isDefault:    $makeDefault,
            createdAt:    new \DateTimeImmutable(),
        );

        $saved = $repo->save($address);
        if ($makeDefault) $repo->setDefault($saved->id, $customer->id);

        return $this->json(['address' => $saved->toArray()], 201);
    }

    public function update(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $repo    = service('customerAddressRepository');
        $existing = $repo->findById($id, $customer->id);
        if (!$existing) return $this->notFound('Address not found.');

        $body    = $this->jsonBody();
        $updated = new CustomerAddress(
            id:           $existing->id,
            customerId:   $existing->customerId,
            label:        $body['label']         ?? $existing->label,
            firstName:    $body['first_name']     ?? $existing->firstName,
            lastName:     $body['last_name']      ?? $existing->lastName,
            phone:        $body['phone']           ?? $existing->phone,
            addressLine1: $body['address_line1']  ?? $existing->addressLine1,
            addressLine2: $body['address_line2']  ?? $existing->addressLine2,
            city:         $body['city']            ?? $existing->city,
            province:     $body['province']        ?? $existing->province,
            postalCode:   $body['postal_code']    ?? $existing->postalCode,
            country:      isset($body['country']) ? strtoupper($body['country']) : $existing->country,
            isDefault:    $existing->isDefault,
            createdAt:    $existing->createdAt,
        );

        $saved = $repo->save($updated);
        if (!empty($body['is_default'])) $repo->setDefault($saved->id, $customer->id);

        return $this->ok(['address' => $saved->toArray()]);
    }

    public function destroy(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        service('customerAddressRepository')->delete($id, $customer->id);

        return $this->ok();
    }

    protected function requireCustomer()
    {
        $header = $this->request->getHeaderLine('Authorization');
        $token  = str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
        if (!$token) return $this->unauthorized('Authentication required.');

        $customer = service('customerRepository')->findByToken($token);
        if (!$customer) return $this->unauthorized('Session expired or invalid.');

        return $customer;
    }
}
