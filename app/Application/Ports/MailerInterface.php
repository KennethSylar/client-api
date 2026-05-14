<?php

namespace App\Application\Ports;

use App\Domain\Orders\Order;

interface MailerInterface
{
    /**
     * @param array[]            $items     Raw item rows (product_name, qty, etc.)
     * @param array<string,string> $settings Site settings (site_name, contact_email, etc.)
     * @param string             $pdfBase64 Base64-encoded invoice PDF
     */
    public function sendOrderConfirmation(Order $order, array $items, array $settings, string $pdfBase64): void;

    /**
     * @param array<string,mixed> $product  Raw product row (name, stock_qty, etc.)
     * @param array<string,string> $settings
     */
    public function sendLowStockAlert(array $product, array $settings): void;

    public function sendContactEnquiry(
        string  $name,
        string  $email,
        ?string $phone,
        ?string $service,
        string  $message,
        array   $settings,
    ): void;
}
