<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Domain\Orders\Customer;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\OrderStatusLogEntry;
use App\Infrastructure\Http\Controllers\BaseController;

class CustomerOrders extends BaseController
{
    public function cancel(string $token): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $order = service('orderRepository')->findByToken($token);
        if (!$order) return $this->notFound('Order not found.');
        if ($order->customerId !== $customer->id) return $this->notFound('Order not found.');

        if ($order->status !== OrderStatus::Pending) {
            return $this->error('Only unpaid orders can be cancelled.', 422);
        }

        service('orderRepository')->updateStatus($order->id, OrderStatus::Cancelled);
        service('orderRepository')->appendStatusLog(new OrderStatusLogEntry(
            orderId:    $order->id,
            fromStatus: $order->status->value,
            toStatus:   OrderStatus::Cancelled->value,
            note:       'Cancelled by customer',
            createdAt:  new \DateTimeImmutable(),
        ));

        return $this->ok();
    }

    public function requestRefund(string $token): \CodeIgniter\HTTP\ResponseInterface
    {
        $customer = $this->requireCustomer();
        if ($customer instanceof \CodeIgniter\HTTP\ResponseInterface) return $customer;

        $order = service('orderRepository')->findByToken($token);
        if (!$order) return $this->notFound('Order not found.');
        if ($order->customerId !== $customer->id) return $this->notFound('Order not found.');

        if ($order->status !== OrderStatus::Delivered) {
            return $this->error('Refund requests can only be made on delivered orders.', 422);
        }

        service('orderRepository')->updateStatus($order->id, OrderStatus::RefundRequested);
        service('orderRepository')->appendStatusLog(new OrderStatusLogEntry(
            orderId:    $order->id,
            fromStatus: $order->status->value,
            toStatus:   OrderStatus::RefundRequested->value,
            note:       'Refund requested by customer',
            createdAt:  new \DateTimeImmutable(),
        ));

        return $this->ok();
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function requireCustomer(): Customer|\CodeIgniter\HTTP\ResponseInterface
    {
        $header = $this->request->getHeaderLine('Authorization');
        $token  = str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
        if (!$token) return $this->unauthorized('Authentication required.');

        $customer = service('customerRepository')->findByToken($token);
        if (!$customer) return $this->unauthorized('Session expired or invalid.');

        return $customer;
    }
}
