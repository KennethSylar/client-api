<?php

namespace App\Controllers\Admin\Shop;

use App\Application\Orders\Commands\RefundOrderCommand;
use App\Application\Orders\Commands\UpdateOrderStatusCommand;
use App\Application\Orders\Queries\GetOrderInvoiceQuery;
use App\Application\Orders\Queries\GetOrderQuery;
use App\Application\Orders\Queries\ListOrdersQuery;
use App\Controllers\BaseController;
use App\Domain\Orders\Order;

class Orders extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        $page    = max(1, (int) ($this->request->getGet('page')     ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 25)));
        $status  = $this->request->getGet('status')  ?? '';
        $search  = trim($this->request->getGet('search') ?? '');

        $result = service('listOrdersHandler')->handle(new ListOrdersQuery(
            page:    $page,
            perPage: $perPage,
            status:  $status,
            search:  $search,
        ));

        return $this->ok([
            'data' => array_map([$this, 'formatOrder'], $result->items),
            'meta' => $result->meta(),
        ]);
    }

    public function show(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $order = service('getOrderHandler')->handle(new GetOrderQuery(id: $id));

        if (!$order) {
            return $this->notFound('Order not found.');
        }

        return $this->ok(array_merge($this->formatOrder($order), [
            'items'      => array_map(fn($i) => $i->toArray(), $order->items),
            'status_log' => array_map(fn($l) => $l->toArray(), $order->statusLog),
        ]));
    }

    public function updateStatus(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $body = $this->jsonBody();

        try {
            service('updateOrderStatusHandler')->handle(new UpdateOrderStatusCommand(
                orderId:         $id,
                status:          $body['status']           ?? '',
                note:            trim($body['note']        ?? ''),
                trackingCarrier: trim($body['tracking_carrier'] ?? '') ?: null,
                trackingNumber:  trim($body['tracking_number']  ?? '') ?: null,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->ok(['status' => $body['status'] ?? '']);
    }

    public function refund(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $note = trim($this->jsonBody()['note'] ?? '');

        try {
            service('refundOrderHandler')->handle(new RefundOrderCommand(
                orderId: $id,
                note:    $note ?: 'Manual refund by admin',
            ));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->ok(['status' => 'refunded']);
    }

    public function invoice(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        try {
            $pdf = service('getOrderInvoiceHandler')->handle(new GetOrderInvoiceQuery(orderId: $id));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', "inline; filename=\"invoice-{$id}.pdf\"")
            ->setBody($pdf);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function formatOrder(Order $o): array
    {
        return [
            'id'               => $o->id,
            'token'            => $o->token,
            'status'           => $o->status->value,
            'first_name'       => $o->firstName,
            'last_name'        => $o->lastName,
            'email'            => $o->email,
            'phone'            => $o->phone,
            'address_line1'    => $o->address->line1,
            'address_line2'    => $o->address->line2,
            'city'             => $o->address->city,
            'province'         => $o->address->province,
            'postal_code'      => $o->address->postalCode,
            'country'          => $o->address->country,
            'subtotal_cents'   => $o->subtotal->amountCents,
            'vat_cents'        => $o->vat->amountCents,
            'shipping_cents'   => $o->shipping->amountCents,
            'total_cents'      => $o->total->amountCents,
            'currency'         => $o->currency,
            'payment_gateway'  => $o->paymentGateway?->value,
            'payment_reference'=> $o->paymentReference,
            'paid_at'          => $o->paidAt?->format('Y-m-d H:i:s'),
            'notes'            => $o->notes,
            'tracking_carrier' => $o->trackingCarrier,
            'tracking_number'  => $o->trackingNumber,
            'created_at'       => $o->createdAt->format('Y-m-d H:i:s'),
            'updated_at'       => $o->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
