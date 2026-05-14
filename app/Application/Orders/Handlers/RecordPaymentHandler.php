<?php

namespace App\Application\Orders\Handlers;

use App\Application\Orders\Commands\RecordPaymentCommand;
use App\Application\Ports\InvoicePdfInterface;
use App\Application\Ports\MailerInterface;
use App\Domain\Core\SettingsRepositoryInterface;
use App\Domain\Orders\OrderRepositoryInterface;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\OrderStatusLogEntry;

final class RecordPaymentHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface  $orders,
        private readonly SettingsRepositoryInterface $settings,
        private readonly InvoicePdfInterface       $invoicePdf,
        private readonly MailerInterface           $mailer,
    ) {}

    public function handle(RecordPaymentCommand $cmd): void
    {
        $order = $this->orders->findById($cmd->orderId);
        if ($order === null || $order->status !== OrderStatus::Pending) {
            return; // idempotent — already processed or not found
        }

        $this->orders->updateStatus($cmd->orderId, OrderStatus::Paid, [
            'payment_reference' => $cmd->reference,
            'paid_at'           => date('Y-m-d H:i:s'),
        ]);

        $this->orders->appendStatusLog(new OrderStatusLogEntry(
            orderId:    $cmd->orderId,
            fromStatus: OrderStatus::Pending->value,
            toStatus:   OrderStatus::Paid->value,
            note:       "Payment confirmed via {$cmd->gateway}. Ref: {$cmd->reference}",
            createdAt:  new \DateTimeImmutable(),
        ));

        // Send confirmation email (fire-and-forget)
        try {
            $settings = $this->settings->getMany([
                'site_name', 'contact_email', 'shop_notification_email',
                'shop_currency', 'shop_vat_enabled', 'shop_vat_rate',
            ]);

            // Map order items to raw arrays the mailer/PDF expect
            $itemRows = array_map(fn($i) => $i->toArray(), $order->items);

            $pdfBytes  = $this->invoicePdf->generate($order, $itemRows, $settings);
            $pdfBase64 = base64_encode($pdfBytes);

            $this->mailer->sendOrderConfirmation($order, $itemRows, $settings, $pdfBase64);
        } catch (\Throwable $e) {
            log_message('error', 'RecordPaymentHandler: confirmation email failed: ' . $e->getMessage());
        }
    }
}
