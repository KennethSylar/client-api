<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Orders\Order;
use App\Domain\Orders\OrderFilter;
use App\Domain\Orders\OrderItem;
use App\Domain\Orders\OrderRefund;
use App\Domain\Orders\OrderRefundItem;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\OrderStatusLogEntry;
use App\Domain\Shared\PaginatedResult;

class MySqlOrderRepository extends AbstractMysqlRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        $row = $this->db->table('shop_orders')->where('id', $id)->get()->getRowArray();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByToken(string $token): ?Order
    {
        $row = $this->db->table('shop_orders')->where('token', $token)->get()->getRowArray();
        return $row ? $this->hydrate($row) : null;
    }

    public function findPaginated(OrderFilter $filter): PaginatedResult
    {
        $builder = $this->db->table('shop_orders')->orderBy('created_at', 'DESC');

        if ($filter->status !== '') {
            $builder->where('status', $filter->status);
        }

        if ($filter->search !== '') {
            $builder->groupStart()
                ->like('email',      $filter->search)
                ->orLike('first_name', $filter->search)
                ->orLike('last_name',  $filter->search)
                ->orLike('token',      $filter->search)
                ->groupEnd();
        }

        $result = $this->paginate($builder, $filter->page, $filter->perPage);

        return new PaginatedResult(
            items:   array_map(fn($r) => Order::fromArray($r), $result->items),
            total:   $result->total,
            page:    $result->page,
            perPage: $result->perPage,
        );
    }

    public function findByCustomer(int $customerId, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $builder = $this->db->table('shop_orders')
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'DESC');

        $result = $this->paginate($builder, $page, $perPage);

        return new PaginatedResult(
            items:   array_map(fn($r) => Order::fromArray($r), $result->items),
            total:   $result->total,
            page:    $result->page,
            perPage: $result->perPage,
        );
    }

    /**
     * Persists a new order including its items and the initial status log entry.
     * Runs inside a transaction. Returns the order with the generated ID.
     */
    public function save(Order $order): Order
    {
        $this->db->transStart();

        $this->db->table('shop_orders')->insert([
            'token'             => $order->token,
            'customer_id'       => $order->customerId,
            'first_name'        => $order->firstName,
            'last_name'         => $order->lastName,
            'email'             => $order->email,
            'phone'             => $order->phone,
            'address_line1'     => $order->address->line1,
            'address_line2'     => $order->address->line2,
            'city'              => $order->address->city,
            'province'          => $order->address->province,
            'postal_code'       => $order->address->postalCode,
            'country'           => $order->address->country,
            'subtotal_cents'    => $order->subtotal->amountCents,
            'vat_cents'         => $order->vat->amountCents,
            'shipping_cents'    => $order->shipping->amountCents,
            'total_cents'       => $order->total->amountCents,
            'currency'          => $order->currency,
            'status'            => $order->status->value,
            'payment_gateway'   => $order->paymentGateway?->value,
            'payment_reference' => $order->paymentReference,
            'paid_at'           => $order->paidAt?->format('Y-m-d H:i:s'),
            'notes'             => $order->notes,
            'tracking_carrier'  => $order->trackingCarrier,
            'tracking_number'   => $order->trackingNumber,
            'created_at'        => $this->now(),
            'updated_at'        => $this->now(),
        ]);

        $orderId = (int) $this->db->insertID();

        foreach ($order->items as $item) {
            $this->db->table('shop_order_items')->insert([
                'order_id'         => $orderId,
                'product_id'       => $item->productId,
                'variant_id'       => $item->variantId,
                'product_name'     => $item->productName,
                'variant_name'     => $item->variantName,
                'qty'              => $item->qty,
                'unit_price_cents' => $item->unitPrice->amountCents,
                'line_total_cents' => $item->lineTotal->amountCents,
                'sku'              => $item->sku,
            ]);
        }

        // Initial status log entry
        $this->db->table('shop_order_status_log')->insert([
            'order_id'    => $orderId,
            'from_status' => null,
            'to_status'   => $order->status->value,
            'note'        => 'Order created',
            'created_at'  => $this->now(),
        ]);

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('Failed to save order — transaction rolled back.');
        }

        return $this->findById($orderId);
    }

    public function updateStatus(int $id, OrderStatus $status, array $extra = []): void
    {
        $this->db->table('shop_orders')->where('id', $id)->update(array_merge([
            'status'     => $status->value,
            'updated_at' => $this->now(),
        ], $extra));
    }

    public function appendStatusLog(OrderStatusLogEntry $entry): void
    {
        $this->db->table('shop_order_status_log')->insert([
            'order_id'    => $entry->orderId,
            'from_status' => $entry->fromStatus,
            'to_status'   => $entry->toStatus,
            'note'        => $entry->note,
            'created_at'  => $entry->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function saveRefund(OrderRefund $refund): OrderRefund
    {
        $this->db->transStart();

        $this->db->table('shop_order_refunds')->insert([
            'order_id'     => $refund->orderId,
            'amount_cents' => $refund->amountCents,
            'note'         => $refund->note,
            'created_at'   => $this->now(),
        ]);

        $refundId = (int) $this->db->insertID();

        foreach ($refund->items as $item) {
            $this->db->table('shop_order_refund_items')->insert([
                'refund_id'     => $refundId,
                'order_item_id' => $item->orderItemId,
                'qty'           => $item->qty,
            ]);
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('Failed to save refund — transaction rolled back.');
        }

        return $this->findRefund($refundId);
    }

    /** @return OrderRefund[] */
    public function findRefundsByOrder(int $orderId): array
    {
        $rows = $this->db->table('shop_order_refunds')
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'ASC')
            ->get()->getResultArray();

        return array_map(fn($r) => $this->hydrateRefund($r), $rows);
    }

    // ── Hydration ─────────────────────────────────────────────────────

    private function hydrate(array $row): Order
    {
        $currency = $row['currency'] ?? 'ZAR';
        $order    = Order::fromArray($row);

        $itemRows = $this->db->table('shop_order_items')
            ->where('order_id', $row['id'])
            ->get()->getResultArray();

        $order->items = array_map(
            fn($i) => OrderItem::fromArray($i, $currency),
            $itemRows
        );

        $logRows = $this->db->table('shop_order_status_log')
            ->where('order_id', $row['id'])
            ->orderBy('created_at', 'ASC')
            ->get()->getResultArray();

        $order->statusLog = array_map(
            fn($l) => OrderStatusLogEntry::fromArray($l),
            $logRows
        );

        $order->refunds = $this->findRefundsByOrder($row['id']);

        return $order;
    }

    private function findRefund(int $refundId): OrderRefund
    {
        $row = $this->db->table('shop_order_refunds')->where('id', $refundId)->get()->getRowArray();
        return $this->hydrateRefund($row);
    }

    private function hydrateRefund(array $row): OrderRefund
    {
        $refund = OrderRefund::fromArray($row);

        $itemRows = $this->db->table('shop_order_refund_items ri')
            ->select('ri.*, oi.product_name, oi.variant_name')
            ->join('shop_order_items oi', 'oi.id = ri.order_item_id', 'left')
            ->where('ri.refund_id', $row['id'])
            ->get()->getResultArray();

        $refund->items = array_map(fn($i) => OrderRefundItem::fromArray($i), $itemRows);

        return $refund;
    }
}
