<?php

namespace App\Infrastructure\Http\Controllers\Shop;

use App\Application\Orders\Queries\GetOrderQuery;
use App\Infrastructure\Http\Controllers\BaseController;

class Orders extends BaseController
{
    public function show(string $token): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($off = $this->shopOffline()) return $off;

        $order = service('getOrderHandler')->handle(new GetOrderQuery(token: $token));

        if (!$order) {
            return $this->notFound('Order not found.');
        }

        return $this->ok([
            'id'               => $order->id,
            'token'            => $order->token,
            'status'           => $order->status->value,
            'first_name'       => $order->firstName,
            'last_name'        => $order->lastName,
            'email'            => $order->email,
            'address_line1'    => $order->address->line1,
            'address_line2'    => $order->address->line2,
            'city'             => $order->address->city,
            'province'         => $order->address->province,
            'postal_code'      => $order->address->postalCode,
            'country'          => $order->address->country,
            'subtotal_cents'   => $order->subtotal->amountCents,
            'vat_cents'        => $order->vat->amountCents,
            'shipping_cents'   => $order->shipping->amountCents,
            'total_cents'      => $order->total->amountCents,
            'currency'         => $order->currency,
            'payment_gateway'  => $order->paymentGateway?->value,
            'paid_at'          => $order->paidAt?->format('Y-m-d H:i:s'),
            'tracking_carrier' => $order->trackingCarrier,
            'tracking_number'  => $order->trackingNumber,
            'created_at'       => $order->createdAt->format('Y-m-d H:i:s'),
            'items'            => array_map(fn($i) => [
                'product_id'       => $i->productId,
                'variant_id'       => $i->variantId,
                'product_slug'     => $i->productSlug,
                'cover_image'      => $i->coverImage,
                'product_name'     => $i->productName,
                'variant_name'     => $i->variantName,
                'qty'              => $i->qty,
                'unit_price_cents' => $i->unitPrice->amountCents,
                'line_total_cents' => $i->lineTotal->amountCents,
            ], $order->items),
        ]);
    }
}
