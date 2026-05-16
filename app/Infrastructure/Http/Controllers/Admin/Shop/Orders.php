<?php

namespace App\Infrastructure\Http\Controllers\Admin\Shop;

use App\Application\Orders\Commands\PartialRefundCommand;
use App\Application\Orders\Commands\RefundOrderCommand;
use App\Application\Orders\Commands\UpdateOrderStatusCommand;
use App\Application\Orders\Queries\GetOrderInvoiceQuery;
use App\Application\Orders\Queries\GetOrderQuery;
use App\Application\Orders\Queries\ListOrdersQuery;
use App\Domain\Orders\Order;
use App\Domain\Orders\RefundableItem;
use App\Infrastructure\Http\Controllers\BaseController;

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
            'refunds'    => array_map(fn($r) => $r->toArray(), $order->refunds),
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

    public function partialRefund(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        $body        = $this->jsonBody();
        $amountCents = (int) round((float) ($body['amount'] ?? 0) * 100);
        $rawItems    = $body['items'] ?? [];
        $note        = trim($body['note'] ?? '');

        $items = array_map(
            fn($i) => new RefundableItem((int) ($i['order_item_id'] ?? 0), (int) ($i['qty'] ?? 0)),
            array_filter($rawItems, fn($i) => !empty($i['order_item_id']) && !empty($i['qty']))
        );

        try {
            service('partialRefundHandler')->handle(new PartialRefundCommand(
                orderId:     $id,
                amountCents: $amountCents,
                items:       array_values($items),
                note:        $note,
            ));
        } catch (\DomainException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->ok(['status' => 'partial_refund_recorded']);
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

    public function export(): \CodeIgniter\HTTP\ResponseInterface
    {
        $status = $this->request->getGet('status') ?? '';
        $search = trim($this->request->getGet('search') ?? '');

        // Fetch all matching orders (no pagination)
        $result = service('listOrdersHandler')->handle(new ListOrdersQuery(
            page:    1,
            perPage: 10000,
            status:  $status,
            search:  $search,
        ));

        $rows = array_map([$this, 'formatOrder'], $result->items);

        $headers = [
            'ID', 'Date', 'Status', 'First Name', 'Last Name', 'Email', 'Phone',
            'Address', 'City', 'Province', 'Postal Code', 'Country',
            'Subtotal', 'VAT', 'Shipping', 'Total', 'Currency',
            'Gateway', 'Payment Ref', 'Paid At', 'Notes',
        ];

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);

        foreach ($rows as $o) {
            fputcsv($out, [
                $o['id'],
                $o['created_at'],
                $o['status'],
                $o['first_name'],
                $o['last_name'],
                $o['email'],
                $o['phone'],
                trim(($o['address_line1'] ?? '') . ' ' . ($o['address_line2'] ?? '')),
                $o['city'],
                $o['province'],
                $o['postal_code'],
                $o['country'],
                number_format($o['subtotal_cents']  / 100, 2, '.', ''),
                number_format($o['vat_cents']       / 100, 2, '.', ''),
                number_format($o['shipping_cents']  / 100, 2, '.', ''),
                number_format($o['total_cents']     / 100, 2, '.', ''),
                $o['currency'],
                $o['payment_gateway'],
                $o['payment_reference'],
                $o['paid_at'],
                $o['notes'],
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $filename = 'orders-' . date('Y-m-d') . '.csv';

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->setBody("\xEF\xBB\xBF" . $csv); // BOM for Excel UTF-8
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
