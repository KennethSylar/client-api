<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Queries\GetOrderInvoiceQuery;
use App\Application\Ports\InvoicePdfInterface;
use App\Domain\Core\SettingsRepositoryInterface;
use App\Domain\Orders\OrderRepositoryInterface;

final class GetOrderInvoiceHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orders,
        private readonly SettingsRepositoryInterface $settings,
        private readonly InvoicePdfInterface        $invoicePdf,
    ) {}

    /** Returns raw PDF bytes. */
    public function handle(GetOrderInvoiceQuery $query): string
    {
        $order = $this->orders->findById($query->orderId);
        if ($order === null) {
            throw new \DomainException('Order not found.');
        }

        $settings = $this->settings->getMany([
            'site_name', 'contact_email', 'contact_phone', 'contact_address',
            'shop_currency', 'shop_vat_enabled', 'shop_vat_rate',
        ]);

        $itemRows = array_map(fn($i) => $i->toArray(), $order->items);

        return $this->invoicePdf->generate($order, $itemRows, $settings);
    }
}
