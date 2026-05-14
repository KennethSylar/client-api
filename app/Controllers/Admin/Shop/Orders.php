<?php

namespace App\Controllers\Admin\Shop;

use App\Controllers\BaseController;

class Orders extends BaseController
{
    /**
     * GET /admin/shop/orders
     *
     * Query params:
     *   ?page=1          (default 1)
     *   ?per_page=25     (default 25, max 100)
     *   ?status=paid     (filter by status)
     *   ?search=jane     (search by name/email/token)
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $db = \Config\Database::connect();

        $page    = max(1, (int)($this->request->getGet('page')     ?? 1));
        $perPage = min(100, max(1, (int)($this->request->getGet('per_page') ?? 25)));
        $status  = $this->request->getGet('status')  ?? '';
        $search  = trim($this->request->getGet('search') ?? '');

        $builder = $db->table('shop_orders')->orderBy('created_at', 'DESC');

        if ($status !== '') {
            $builder->where('status', $status);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $builder->groupStart()
                ->like('email',      $search)
                ->orLike('first_name', $search)
                ->orLike('last_name',  $search)
                ->orLike('token',      $search)
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);
        $rows  = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return $this->ok([
            'data'  => array_map([$this, 'formatOrder'], $rows),
            'meta'  => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int)ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * GET /admin/shop/orders/:id
     */
    public function show(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db    = \Config\Database::connect();
        $order = $db->table('shop_orders')->where('id', $id)->get()->getRowArray();

        if (!$order) return $this->notFound('Order not found.');

        $items = $db->table('shop_order_items')
            ->where('order_id', $id)
            ->get()->getResultArray();

        $log = $db->table('shop_order_status_log')
            ->where('order_id', $id)
            ->orderBy('created_at', 'ASC')
            ->get()->getResultArray();

        return $this->ok(array_merge($this->formatOrder($order), [
            'items'      => array_map(fn($i) => [
                'id'               => (int)$i['id'],
                'product_name'     => $i['product_name'],
                'variant_name'     => $i['variant_name'],
                'qty'              => (int)$i['qty'],
                'unit_price_cents' => (int)$i['unit_price_cents'],
                'line_total_cents' => (int)$i['line_total_cents'],
                'sku'              => $i['sku'],
            ], $items),
            'status_log' => array_map(fn($l) => [
                'from'       => $l['from_status'],
                'to'         => $l['to_status'],
                'note'       => $l['note'],
                'created_at' => $l['created_at'],
            ], $log),
        ]));
    }

    /**
     * PATCH /admin/shop/orders/:id/status
     *
     * Body: { "status": "shipped", "note": "Tracking: ABC123" }
     */
    public function updateStatus(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db    = \Config\Database::connect();
        $order = $db->table('shop_orders')->where('id', $id)->get()->getRowArray();

        if (!$order) return $this->notFound('Order not found.');

        $body      = $this->jsonBody();
        $newStatus = $body['status'] ?? '';
        $note      = trim($body['note'] ?? '');

        $allowed = ['pending','paid','processing','shipped','delivered','cancelled','refunded'];
        if (!in_array($newStatus, $allowed, true)) {
            return $this->error('Invalid status.', 400);
        }

        $updateFields = [
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($newStatus === 'shipped') {
            $carrier = trim($body['tracking_carrier'] ?? '');
            $number  = trim($body['tracking_number']  ?? '');
            if ($carrier !== '') $updateFields['tracking_carrier'] = $carrier;
            if ($number  !== '') $updateFields['tracking_number']  = $number;
        }

        $db->table('shop_orders')->where('id', $id)->update($updateFields);

        $db->table('shop_order_status_log')->insert([
            'order_id'    => $id,
            'from_status' => $order['status'],
            'to_status'   => $newStatus,
            'note'        => $note ?: null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->ok(['status' => $newStatus]);
    }

    /**
     * POST /admin/shop/orders/:id/refund
     *
     * Body: { "note": "Customer requested" }
     * Marks order as refunded and restores stock.
     */
    public function refund(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db    = \Config\Database::connect();
        $order = $db->table('shop_orders')->where('id', $id)->get()->getRowArray();

        if (!$order) return $this->notFound('Order not found.');

        if (!in_array($order['status'], ['paid','processing','shipped','delivered'], true)) {
            return $this->error('Order cannot be refunded in its current status.', 400);
        }

        $note = trim($this->jsonBody()['note'] ?? '');

        $db->transStart();

        $db->table('shop_orders')->where('id', $id)->update([
            'status'     => 'refunded',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $db->table('shop_order_status_log')->insert([
            'order_id'    => $id,
            'from_status' => $order['status'],
            'to_status'   => 'refunded',
            'note'        => $note ?: 'Manual refund by admin',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Restore stock
        $items = $db->table('shop_order_items')->where('order_id', $id)->get()->getResultArray();
        foreach ($items as $item) {
            if ($item['variant_id']) {
                $variant = $db->table('shop_product_variants')->where('id', $item['variant_id'])->get()->getRowArray();
                if ($variant && $variant['track_stock']) {
                    $db->table('shop_product_variants')
                        ->where('id', $item['variant_id'])
                        ->set('stock_qty', "stock_qty + {$item['qty']}", false)
                        ->update();
                    \App\Controllers\Admin\Shop\Stock::logAdjustment(
                        $db, (int)$item['product_id'], (int)$item['variant_id'], $item['qty'], 'refund', $id
                    );
                }
            } elseif ($item['product_id']) {
                $product = $db->table('shop_products')->where('id', $item['product_id'])->get()->getRowArray();
                if ($product && $product['track_stock']) {
                    $db->table('shop_products')
                        ->where('id', $item['product_id'])
                        ->set('stock_qty', "stock_qty + {$item['qty']}", false)
                        ->update();
                    \App\Controllers\Admin\Shop\Stock::logAdjustment(
                        $db, (int)$item['product_id'], null, $item['qty'], 'refund', $id
                    );
                }
            }
        }

        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->error('Refund failed. Please try again.', 500);
        }

        return $this->ok(['status' => 'refunded']);
    }

    /**
     * GET /admin/shop/orders/:id/invoice
     * Streams a PDF invoice for the order.
     */
    public function invoice(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $db    = \Config\Database::connect();
        $order = $db->table('shop_orders')->where('id', $id)->get()->getRowArray();

        if (!$order) return $this->notFound('Order not found.');

        $items = $db->table('shop_order_items')
            ->where('order_id', $id)
            ->get()->getResultArray();

        $settings = $this->loadSettings($db);

        $pdf = \App\Services\InvoicePdf::generate($order, $items, $settings);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', "inline; filename=\"invoice-{$id}.pdf\"")
            ->setBody($pdf);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function formatOrder(array $order): array
    {
        return [
            'id'              => (int)$order['id'],
            'token'           => $order['token'],
            'status'          => $order['status'],
            'first_name'      => $order['first_name'],
            'last_name'       => $order['last_name'],
            'email'           => $order['email'],
            'phone'           => $order['phone'],
            'address_line1'   => $order['address_line1'],
            'address_line2'   => $order['address_line2'],
            'city'            => $order['city'],
            'province'        => $order['province'],
            'postal_code'     => $order['postal_code'],
            'country'         => $order['country'],
            'subtotal_cents'  => (int)$order['subtotal_cents'],
            'vat_cents'       => (int)$order['vat_cents'],
            'shipping_cents'  => (int)$order['shipping_cents'],
            'total_cents'     => (int)$order['total_cents'],
            'currency'        => $order['currency'],
            'payment_gateway' => $order['payment_gateway'],
            'payment_reference'=> $order['payment_reference'],
            'paid_at'          => $order['paid_at'],
            'notes'            => $order['notes'],
            'tracking_carrier' => $order['tracking_carrier'] ?? null,
            'tracking_number'  => $order['tracking_number']  ?? null,
            'created_at'       => $order['created_at'],
            'updated_at'       => $order['updated_at'],
        ];
    }

    private function loadSettings(\CodeIgniter\Database\BaseConnection $db): array
    {
        $rows = $db->table('settings')->whereIn('key', [
            'site_name', 'contact_email', 'contact_phone', 'contact_address',
            'shop_currency', 'shop_vat_enabled', 'shop_vat_rate',
        ])->get()->getResultArray();

        return array_column($rows, 'value', 'key');
    }
}
