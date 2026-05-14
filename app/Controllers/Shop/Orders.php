<?php

namespace App\Controllers\Shop;

use App\Controllers\BaseController;

class Orders extends BaseController
{
    /**
     * GET /shop/orders/:token
     * Returns the order for the confirmation page.
     * No auth required — the token IS the authentication.
     */
    public function show(string $token): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $db    = \Config\Database::connect();
        $order = $db->table('shop_orders')->where('token', $token)->get()->getRowArray();

        if (!$order) {
            return $this->notFound('Order not found.');
        }

        $items = $db->table('shop_order_items')
            ->where('order_id', $order['id'])
            ->get()->getResultArray();

        return $this->ok([
            'id'             => (int)$order['id'],
            'token'          => $order['token'],
            'status'         => $order['status'],
            'first_name'     => $order['first_name'],
            'last_name'      => $order['last_name'],
            'email'          => $order['email'],
            'address_line1'  => $order['address_line1'],
            'address_line2'  => $order['address_line2'],
            'city'           => $order['city'],
            'province'       => $order['province'],
            'postal_code'    => $order['postal_code'],
            'country'        => $order['country'],
            'subtotal_cents' => (int)$order['subtotal_cents'],
            'vat_cents'      => (int)$order['vat_cents'],
            'shipping_cents' => (int)$order['shipping_cents'],
            'total_cents'    => (int)$order['total_cents'],
            'currency'       => $order['currency'],
            'payment_gateway'  => $order['payment_gateway'],
            'paid_at'          => $order['paid_at'],
            'tracking_carrier' => $order['tracking_carrier'] ?? null,
            'tracking_number'  => $order['tracking_number']  ?? null,
            'created_at'       => $order['created_at'],
            'items'          => array_map(fn($i) => [
                'product_name'     => $i['product_name'],
                'variant_name'     => $i['variant_name'],
                'qty'              => (int)$i['qty'],
                'unit_price_cents' => (int)$i['unit_price_cents'],
                'line_total_cents' => (int)$i['line_total_cents'],
            ], $items),
        ]);
    }
}
